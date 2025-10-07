<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\HonorEvent;
use App\Models\User;
use App\Support\DatabaseUtils;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;

class AttendanceController extends Controller
{
    /**
     * Actualiza asistencia/comportamiento de un signup y aplica honor correspondiente.
     * Rol permitido: creador/manager de la mesa o admin.
     */
    public function update(Request $request, GameTable $mesa, Signup $signup): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Normalizamos selects con valor "sin cambios" antes de validar.
        if ($request->input('attended') === '_keep_') {
            $request->request->remove('attended');
        }
        if ($request->input('behavior') === '_keep_') {
            $request->request->remove('behavior');
        }

        $isOwner = (int) ($mesa->created_by ?? 0) === (int) $user->id;
        $isManager = (int) ($mesa->manager_id ?? 0) === (int) $user->id;
        $isAdmin = (string) ($user->role ?? '') === 'admin';
        abort_unless($isOwner || $isManager || $isAdmin, 403);

        // Aseguramos que el signup pertenezca a la mesa indicada
        abort_unless((int) $signup->game_table_id === (int) $mesa->id, 404);

        // Validación nativa (sin helpers externos)
        $data = $request->validate([
            'attended' => ['nullable', 'boolean'],
            'no_show' => ['nullable', 'boolean'],
            'behavior' => ['nullable', 'in:good,regular,bad'],
        ]);

        $hasAttendedColumn = $this->signupHasColumn('attended');
        $hasBehaviorColumn = $this->signupHasColumn('behavior');
        $hasLegacyConfirmedAt = $this->signupHasColumn('attendance_confirmed_at');
        $hasLegacyConfirmedBy = $this->signupHasColumn('attendance_confirmed_by');
        $hasLegacyNoShowAt = $this->signupHasColumn('no_show_at');
        $hasLegacyNoShowBy = $this->signupHasColumn('no_show_by');

        DB::transaction(function () use (
            $data,
            $mesa,
            $signup,
            $user,
            $hasAttendedColumn,
            $hasBehaviorColumn,
            $hasLegacyConfirmedAt,
            $hasLegacyConfirmedBy,
            $hasLegacyNoShowAt,
            $hasLegacyNoShowBy
        ) {
            // Bloqueo pesimista para consistencia en hosting compartido
            /** @var Signup $row */
            $row = DatabaseUtils::applyPessimisticLock(
                Signup::query()
                    ->whereKey($signup->id)
            )->firstOrFail();

            $prevAttended = $this->resolveAttendedState(
                $row,
                $hasAttendedColumn,
                $hasLegacyConfirmedAt,
                $hasLegacyNoShowAt
            );
            $prevBehavior = $this->resolveBehaviorState($row, $hasBehaviorColumn);

            // Persistimos cambios de estado en Signup (si vinieron en la request)
            $dirty = false;
            $honorTouched = false;
            $nowStamp = null;
            if (array_key_exists('attended', $data)) {
                $nowAttended = (bool) $data['attended'];

                if ($hasAttendedColumn && $row->attended !== $nowAttended) {
                    $row->attended = $nowAttended;
                    $dirty = true;
                }

                if ($hasLegacyConfirmedAt) {
                    $nowStamp ??= now();
                    $row->attendance_confirmed_at = $nowAttended ? $nowStamp : null;
                    $dirty = true;
                }
                if ($hasLegacyConfirmedBy && $row->attendance_confirmed_by !== ($nowAttended ? $user->id : null)) {
                    $row->attendance_confirmed_by = $nowAttended ? $user->id : null;
                    $dirty = true;
                }
                if ($nowAttended) {
                    if ($hasLegacyNoShowAt && $row->no_show_at !== null) {
                        $row->no_show_at = null;
                        $dirty = true;
                    }
                    if ($hasLegacyNoShowBy && $row->no_show_by !== null) {
                        $row->no_show_by = null;
                        $dirty = true;
                    }
                }
            }
            if ($hasBehaviorColumn && array_key_exists('behavior', $data)) {
                $newBehaviorValue = (string) $data['behavior'];
                if ((string) ($row->behavior ?? '') !== $newBehaviorValue) {
                    $row->behavior = $newBehaviorValue;
                    $dirty = true;
                }
            }
            if ($dirty) {
                $row->save();
            }

            $dirty = false;

            $mesaId = (int) $mesa->id;
            $signupId = (int) $row->id;
            /** @var User $target */
            $target = $row->user()->select(['id'])->firstOrFail();

            $newBehavior = $this->resolveBehaviorState($row, $hasBehaviorColumn);

            // ---------------------------
            // Reglas de HONOR
            // ---------------------------

            // 1) Asistencia: +10 al confirmar; -10 al desconfirmar (ambas idempotentes)
            if (array_key_exists('attended', $data)) {
                $nowAttended = (bool) $data['attended'];

                if ($nowAttended && $prevAttended !== true) {
                    $honorTouched = $this->addHonorSafe(
                        $target,
                        +10,
                        HonorEvent::R_ATTEND_OK,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:attended"
                    ) || $honorTouched;
                }

                if (!$nowAttended && $prevAttended === true) {
                    $honorTouched = $this->addHonorSafe(
                        $target,
                        -10,
                        HonorEvent::R_ATTEND_UNDO,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:attended:undo"
                    ) || $honorTouched;
                }
            }

            // 2) No show: -20 (solo si NO vino attended=1 en esta misma request)
            if (($data['no_show'] ?? false) && !($data['attended'] ?? false)) {
                if ($hasLegacyNoShowAt || $hasLegacyNoShowBy) {
                    $nowStamp ??= now();
                    if ($hasLegacyNoShowAt) {
                        $row->no_show_at = $nowStamp;
                        $dirty = true;
                    }
                    if ($hasLegacyNoShowBy) {
                        $row->no_show_by = $user->id;
                        $dirty = true;
                    }
                    if ($hasLegacyConfirmedAt && $row->attendance_confirmed_at !== null) {
                        $row->attendance_confirmed_at = null;
                        $dirty = true;
                    }
                    if ($hasLegacyConfirmedBy && $row->attendance_confirmed_by !== null) {
                        $row->attendance_confirmed_by = null;
                        $dirty = true;
                    }
                    if ($hasAttendedColumn && $row->attended !== false) {
                        $row->attended = false;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $row->save();
                    }
                }

                $honorTouched = $this->addHonorSafe(
                    $target,
                    -20,
                    HonorEvent::R_NO_SHOW,
                    ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                    "mesa:{$mesaId}:signup:{$signupId}:no_show"
                ) || $honorTouched;
            }

            // 3) Comportamiento: transición entre good/regular/bad con undos correctos
            if ($hasBehaviorColumn && $newBehavior !== $prevBehavior) {
                if ($newBehavior === 'good') {
                    if ($prevBehavior === 'bad') {
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            +10,
                            HonorEvent::R_BEHAV_UNDO_BAD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:bad"
                        ) || $honorTouched;
                    }
                    $honorTouched = $this->addHonorSafe(
                        $target,
                        +10,
                        HonorEvent::R_BEHAV_GOOD,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:behavior:good"
                    ) || $honorTouched;

                } elseif ($newBehavior === 'bad') {
                    if ($prevBehavior === 'good') {
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            -10,
                            HonorEvent::R_BEHAV_UNDO_GOOD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:good"
                        ) || $honorTouched;
                    }
                    $honorTouched = $this->addHonorSafe(
                        $target,
                        -10,
                        HonorEvent::R_BEHAV_BAD,
                        ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                        "mesa:{$mesaId}:signup:{$signupId}:behavior:bad"
                    ) || $honorTouched;

                } else { // regular
                    if ($prevBehavior === 'good') {
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            -10,
                            HonorEvent::R_BEHAV_UNDO_GOOD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:good"
                        ) || $honorTouched;
                    } elseif ($prevBehavior === 'bad') {
                        $honorTouched = $this->addHonorSafe(
                            $target,
                            +10,
                            HonorEvent::R_BEHAV_UNDO_BAD,
                            ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $user->id],
                            "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:bad"
                        ) || $honorTouched;
                    }
                }
            }

            if ($honorTouched && method_exists($target, 'refreshHonorAggregate')) {
                // Recalcula y persiste el total si la columna users.honor existe.
                $target->refreshHonorAggregate(true);
            }
        });

        return back()->with('ok', 'Asistencia/comportamiento actualizados y honor aplicado.');
    }

    /**
     * Inserta honor de manera segura e idempotente por (user_id, slug).
     * Si el método addHonor existe en User, lo usa; si no, cae a firstOrCreate().
     */
    private function addHonorSafe(User $user, int $points, string $reason, array $meta, string $slug): bool
    {
        try {
            if (method_exists($user, 'addHonor')) {
                $event = $user->addHonor($points, $reason, $meta, $slug);
                return $event->wasRecentlyCreated;
            }

            $event = HonorEvent::firstOrCreate(
                ['user_id' => $user->id, 'slug' => $slug],
                ['points' => $points, 'reason' => $reason, 'meta' => $meta]
            );
            return $event->wasRecentlyCreated;
        } catch (QueryException $e) {
            if ($this->isMissingHonorTable($e)) {
                // Entornos sin la tabla honor_events: omite silenciosamente.
                return false;
            }

            throw $e;
        }
    }

    private function isMissingHonorTable(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');
        $exceptionCode = (string) $e->getCode();
        $message = strtolower((string) $e->getMessage());

        $states = ['42S02', '42P01']; // MySQL/MariaDB, PostgreSQL
        if (in_array($sqlState, $states, true) || in_array($exceptionCode, $states, true)) {
            return true;
        }

        if ($driverCode === '1146') { // MySQL/MariaDB table missing
            return true;
        }

        if ($driverCode === '1' && str_contains($message, 'no such table')) {
            // SQLite "no such table: honor_events"
            return true;
        }

        if (str_contains($message, 'honor_events') &&
            (str_contains($message, 'does not exist') ||
                str_contains($message, "doesn't exist") ||
                str_contains($message, 'not found'))
        ) {
            // Fallback para otros drivers
            return true;
        }

        return false;
    }

    private function signupHasColumn(string $column): bool
    {
        static $cache = [];

        if (!array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn((new Signup())->getTable(), $column);
        }

        return $cache[$column];
    }

    private function resolveAttendedState(
        Signup $signup,
        bool $hasAttendedColumn,
        bool $hasLegacyConfirmedAt,
        bool $hasLegacyNoShowAt
    ): ?bool {
        if ($hasAttendedColumn) {
            $value = $signup->getAttribute('attended');
            return $value === null ? null : (bool) $value;
        }

        if ($hasLegacyConfirmedAt && $signup->getAttribute('attendance_confirmed_at')) {
            return true;
        }

        if ($hasLegacyNoShowAt && $signup->getAttribute('no_show_at')) {
            return false;
        }

        return null;
    }

    private function resolveBehaviorState(Signup $signup, bool $hasBehaviorColumn): string
    {
        if ($hasBehaviorColumn) {
            $value = $signup->getAttribute('behavior');
            return $value !== null ? (string) $value : 'regular';
        }

        return 'regular';
    }
}
