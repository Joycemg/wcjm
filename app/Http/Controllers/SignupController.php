<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SignupController extends Controller
{
    private const MAX_RETRIES = 3;

    /**
     * Anotar / mover voto a una mesa.
     * - Idempotente ante duplicados (unique user_id).
     * - Reintenta frente a deadlocks/lock timeouts (hosting compartido).
     */
    public function store(Request $request, GameTable $mesa): JsonResponse|RedirectResponse
    {
        $user = $this->requireUser($request);

        // Chequeo rápido fuera de transacción (se valida otra vez adentro)
        if (!$this->mesaIsOpenNow($mesa)) {
            return $this->respond($request, false, 'Esta mesa no está abierta para votar.', 422);
        }

        $doSwitch = $request->boolean('switch');

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = DB::transaction(function () use ($user, $mesa, $doSwitch) {
                    // Lock de la mesa para consistencia
                    $mesaRow = GameTable::query()
                        ->whereKey($mesa->getKey())
                        ->lockForUpdate()
                        ->first();

                    if (!$mesaRow || !$this->mesaIsOpenNow($mesaRow)) {
                        return ['status' => 'closed'];
                    }

                    // Lock del signup del usuario (si existe)
                    $existing = Signup::query()
                        ->where('user_id', $user->id)
                        ->lockForUpdate()
                        ->first();

                    // Ya está inscripto en esta mesa
                    if ($existing && (int) $existing->game_table_id === (int) $mesaRow->id) {
                        return ['status' => 'already'];
                    }

                    // Está en otra mesa
                    if ($existing && (int) $existing->game_table_id !== (int) $mesaRow->id) {
                        if ($doSwitch) {
                            $from = (int) $existing->game_table_id;

                            // Mover
                            $existing->game_table_id = (int) $mesaRow->id;
                            $existing->save();

                            // Tocar updated_at de ambas mesas (barato, sin eventos)
                            $now = now();
                            DB::table('game_tables')->where('id', $from)->update(['updated_at' => $now]);
                            DB::table('game_tables')->where('id', $mesaRow->id)->update(['updated_at' => $now]);

                            return ['status' => 'switched', 'from' => $from];
                        }

                        return [
                            'status' => 'other',
                            'otherMesaId' => (int) $existing->game_table_id,
                        ];
                    }

                    // Crear signup
                    Signup::create([
                        'game_table_id' => (int) $mesaRow->id,
                        'user_id' => (int) $user->id,
                        'is_counted' => 1,
                        'is_manager' => 0,
                    ]);

                    // Tocar updated_at de la mesa destino
                    DB::table('game_tables')->where('id', $mesaRow->id)->update(['updated_at' => now()]);

                    return ['status' => 'created'];
                }, 10);

                return match ($result['status']) {
                    'created' => $this->respond($request, true, '¡Listo! Te anotaste.', 201, ['mesa_id' => $mesa->id]),
                    'already' => $this->respond($request, true, 'Ya estás anotado en esta mesa.', 200, ['mesa_id' => $mesa->id]),
                    'switched' => $this->respond($request, true, 'Movimos tu voto a esta mesa.', 200, [
                        'mesa_id' => $mesa->id,
                        'from_mesa_id' => $result['from'] ?? null,
                    ]),
                    'other' => $this->respond(
                        $request,
                        false,
                        'Solo podés votar en una mesa. Activá "mover" para traer tu voto a esta.',
                        409,
                        ['other_mesa_id' => $result['otherMesaId'] ?? null],
                        ['X-Conflict-Mesa-Id' => (string) ($result['otherMesaId'] ?? '')]
                    ),
                    'closed' => $this->respond($request, false, 'Esta mesa no está abierta para votar.', 422),
                    default => $this->respond($request, false, 'No se pudo procesar tu voto.', 500),
                };

            } catch (QueryException $e) {
                // Duplicado (unique user_id) → resolver amigablemente
                if ($this->isUniqueViolation($e)) {
                    $current = Signup::where('user_id', $user->id)->first();
                    if ($current && (int) $current->game_table_id === (int) $mesa->id) {
                        return $this->respond($request, true, 'Ya estás anotado en esta mesa.', 200, ['mesa_id' => $mesa->id]);
                    }
                    if ($current) {
                        return $this->respond(
                            $request,
                            false,
                            'Solo podés votar en una mesa. Activá "mover" para traer tu voto a esta.',
                            409,
                            ['other_mesa_id' => (int) $current->game_table_id],
                            ['X-Conflict-Mesa-Id' => (string) (int) $current->game_table_id]
                        );
                    }
                }

                // Deadlock / lock timeout → backoff y reintentar
                if ($this->isDeadlockOrLockTimeout($e) && $attempt < self::MAX_RETRIES) {
                    // Jitter pequeño para hosting compartido
                    usleep(random_int(20_000, 120_000));
                    continue;
                }

                throw $e;
            }
        }

        return $this->respond($request, false, 'No se pudo procesar tu voto.', 500);
    }

    /**
     * Retirar voto de una mesa.
     */
    public function destroy(Request $request, GameTable $mesa): JsonResponse|RedirectResponse
    {
        $user = $this->requireUser($request);

        $deleted = 0;
        DB::transaction(function () use ($user, $mesa, &$deleted) {
            $deleted = Signup::where('game_table_id', (int) $mesa->id)
                ->where('user_id', (int) $user->id)
                ->lockForUpdate()
                ->delete();

            if ($deleted) {
                DB::table('game_tables')->where('id', $mesa->id)->update(['updated_at' => now()]);
            }
        });

        return $this->respond(
            $request,
            true,
            $deleted ? 'Listo, retiraste tu voto.' : 'No estabas anotado en esta mesa.',
            200,
            ['mesa_id' => $mesa->id]
        );
    }

    /* ========================= Helpers ========================= */

    /** Devuelve el usuario autenticado tipado o aborta 403 (evita warnings del IDE) */
    private function requireUser(Request $request): User
    {
        $u = $request->user();
        abort_unless($u instanceof User, 403);
        return $u;
    }

    /** ¿La mesa está abierta "ahora"? Usa huso de pantalla del Controller. */
    private function mesaIsOpenNow(GameTable $mesa): bool
    {
        if (!$mesa->is_open) {
            return false;
        }

        $opensRaw = $mesa->opens_at;
        if ($opensRaw === null) {
            return true;
        }

        $tz = $this->tz();
        $openAt = $opensRaw instanceof CarbonInterface
            ? Carbon::instance($opensRaw)->timezone($tz)
            : Carbon::parse((string) $opensRaw, $tz);

        return $this->nowTz()->greaterThanOrEqualTo($openAt);
    }

    /**
     * Respuesta JSON/redirect estándar (usa helpers del Controller base).
     */
    private function respond(
        Request $request,
        bool $success,
        string $message,
        int $status = 200,
        array $extra = [],
        array $headers = []
    ): JsonResponse|RedirectResponse {
        if ($request->wantsJson()) {
            $res = $success
                ? $this->jsonOk(array_merge(['message' => $message], $extra), [], $status)
                : $this->jsonFail($message, $status, [], $extra);
            $res->headers->add($headers);
            return $res;
        }

        return back()->with($success ? 'ok' : 'err', $message);
    }

    /** Detecta violación de UNIQUE para MySQL/MariaDB/Postgres/SQLite. */
    private function isUniqueViolation(QueryException $e): bool
    {
        $info = $e->errorInfo ?? [null, null, null];
        $state = $info[0] ?? null;
        $code = (int) ($info[1] ?? 0);
        $msg = strtolower((string) ($info[2] ?? $e->getMessage()));

        // SQLSTATE comunes
        if (in_array($state, ['23000', '23505'], true)) {
            return true;
        }

        // MySQL/MariaDB
        if (in_array($code, [1062, 1169, 1557], true)) {
            return true;
        }

        // SQLite
        if ($code === 19 || str_contains($msg, 'unique constraint failed')) {
            return true;
        }

        return false;
    }

    /** Detecta deadlock o lock timeout cross-DB. */
    private function isDeadlockOrLockTimeout(QueryException $e): bool
    {
        $info = $e->errorInfo ?? [null, null, ''];
        $state = $info[0] ?? null;
        $code = (int) ($info[1] ?? 0);
        $msg = strtolower((string) ($info[2] ?? $e->getMessage()));

        // MySQL/MariaDB
        if (in_array($code, [1205, 1213], true)) {
            return true;
        }

        // Postgres
        if (in_array($state, ['40001', '40P01', '55P03'], true)) {
            return true;
        }

        // Mensajes genéricos
        return str_contains($msg, 'deadlock') || str_contains($msg, 'lock wait timeout');
    }
}
