<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\GameTableClosed;
use App\Models\GameTable;
use App\Models\Signup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RecordVoteHistory implements ShouldQueue
{
    use InteractsWithQueue;

    /** Reintentos y límites seguros para hosting compartido */
    public int $tries = 3;           // total de intentos
    public int $timeout = 20;        // segundos por intento
    public int $maxExceptions = 3;   // excepciones antes de fallar definitivo

    /**
     * Backoff exponencial simple entre reintentos.
     * Laravel >=9 soporta array; en <9 podés dejar un int fijo.
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(GameTableClosed $e): void
    {
        // Si no es el primer cierre o está desactivado por config, no hacemos nada
        if (!$e->firstClose || !config('mesas.record_history_via_listener', true)) {
            return;
        }

        // La tabla debe existir
        if (!Schema::hasTable('vote_histories')) {
            Log::notice('RecordVoteHistory: tabla vote_histories no existe; se omite.', ['table_id' => $e->tableId]);
            return;
        }

        // Cargar la mesa (sólo lo necesario)
        /** @var GameTable|null $mesa */
        $mesa = GameTable::query()
            ->select('id', 'title', 'capacity', 'closed_at')
            ->find($e->tableId);

        if (!$mesa) {
            Log::warning('RecordVoteHistory: mesa no encontrada', ['table_id' => $e->tableId]);
            return;
        }

        // Momento de cierre: usa el de evento o el persistido; cae a now() si falta
        $closedAt = $mesa->closed_at
            ?: ($e->closedAtIso ? Carbon::parse($e->closedAtIso)->utc() : now()->utc());

        // Determinar jugadores finales (dentro de capacidad), por orden de inscripción
        $userIds = $this->finalPlayersUserIds($mesa);

        if (empty($userIds)) {
            // Nada que registrar
            return;
        }

        // Detección de columnas opcionales para construir filas y uniqueBy
        $hasKind = Schema::hasColumn('vote_histories', 'kind');
        $hasHappened = Schema::hasColumn('vote_histories', 'happened_at');
        $hasClosed = Schema::hasColumn('vote_histories', 'closed_at');
        $hasCreated = Schema::hasColumn('vote_histories', 'created_at');
        $hasUpdated = Schema::hasColumn('vote_histories', 'updated_at');

        // Clave idempotente: con 'kind' usamos (user_id,game_table_id,kind), si no (user_id,game_table_id)
        $uniqueBy = $hasKind ? ['user_id', 'game_table_id', 'kind'] : ['user_id', 'game_table_id'];

        // Columnas a actualizar en caso de upsert existente
        $updateCols = ['game_title'];
        if ($hasHappened) {
            $updateCols[] = 'happened_at';
        } elseif ($hasClosed) {
            $updateCols[] = 'closed_at';
        }
        if ($hasUpdated) {
            $updateCols[] = 'updated_at';
        }

        // Construcción y upsert en chunks
        $title = (string) ($mesa->title ?? 'Mesa');
        $nowTs = now();

        $chunkSize = 500;
        foreach (array_chunk($userIds, $chunkSize) as $chunk) {
            $rows = [];
            foreach ($chunk as $uid) {
                $row = [
                    'user_id' => (int) $uid,
                    'game_table_id' => (int) $mesa->id,
                    'game_title' => $title,
                ];

                if ($hasKind) {
                    $row['kind'] = 'close';
                }
                if ($hasHappened) {
                    $row['happened_at'] = $closedAt;
                } elseif ($hasClosed) {
                    $row['closed_at'] = $closedAt;
                }
                if ($hasCreated) {
                    $row['created_at'] = $nowTs;
                }
                if ($hasUpdated) {
                    $row['updated_at'] = $nowTs;
                }

                $rows[] = $row;
            }

            try {
                DB::table('vote_histories')->upsert($rows, $uniqueBy, $updateCols);
            } catch (Throwable $ex) {
                // En algunos hosts antiguos, upsert puede fallar: degradar a updateOrInsert
                Log::warning('RecordVoteHistory: upsert falló; degradando a updateOrInsert por fila.', [
                    'table_id' => $mesa->id,
                    'msg' => $ex->getMessage(),
                ]);

                foreach ($rows as $row) {
                    $match = [
                        'user_id' => $row['user_id'],
                        'game_table_id' => $row['game_table_id'],
                    ];
                    if ($hasKind) {
                        $match['kind'] = $row['kind'] ?? 'close';
                    }

                    $data = $row;
                    unset($data['user_id'], $data['game_table_id']);
                    if ($hasKind) {
                        unset($data['kind']);
                    }

                    try {
                        DB::table('vote_histories')->updateOrInsert($match, $data);
                    } catch (Throwable $e2) {
                        Log::error('RecordVoteHistory: updateOrInsert falló', [
                            'table_id' => $mesa->id,
                            'user_id' => $row['user_id'] ?? null,
                            'msg' => $e2->getMessage(),
                        ]);
                        // Re-lanzamos para que el job pueda reintentar (según $tries/backoff)
                        throw $e2;
                    }
                }
            }
        }
    }

    /**
     * Obtiene los IDs de usuarios finales (dentro de capacidad) por orden de inscripción.
     * Usa GameTable::finalPlayersUserIds() si existe; si no, realiza la consulta equivalente.
     *
     * @return list<int>
     */
    private function finalPlayersUserIds(GameTable $mesa): array
    {
        // Si el modelo tiene el helper, úsalo (ya lo agregaste en tu GameTable mejorado)
        if (method_exists($mesa, 'finalPlayersUserIds')) {
            try {
                /** @var list<int> $ids */
                $ids = $mesa->finalPlayersUserIds();
                return $ids;
            } catch (Throwable $e) {
                Log::warning('RecordVoteHistory: fallo finalPlayersUserIds(); se usará fallback.', [
                    'table_id' => $mesa->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: primeros N inscriptos contados por orden
        $cap = max(1, (int) ($mesa->capacity ?? 1));

        return DB::table('signups')
            ->where('game_table_id', $mesa->id)
            ->where('is_counted', 1)
            ->orderBy('created_at', 'asc')
            ->limit($cap)
            ->pluck('user_id')
            ->map(static fn($v) => (int) $v)
            ->all();
    }
}
