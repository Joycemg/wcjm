<?php
// app/Listeners/RecordVoteHistory.php
namespace App\Listeners;

use App\Events\GameTableClosed;
use App\Models\GameTable;
use App\Models\Signup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordVoteHistory implements ShouldQueue
{
    use InteractsWithQueue;

    /** Reintentos y backoff seguros para hosting compartido */
    public int $tries = 3;
    public int $backoff = 10;

    public function handle(GameTableClosed $e): void
    {
        // Si no es el primer cierre o lo desactivaste por config, no hacer nada
        if (!$e->firstClose || !config('mesas.record_history_via_listener', true)) {
            return;
        }

        // Cargar SOLO lo necesario
        $mesa = GameTable::query()
            ->select('id', 'title', 'closed_at')
            ->find($e->tableId);

        if (!$mesa) {
            Log::warning('RecordVoteHistory: mesa no encontrada', ['table_id' => $e->tableId]);
            return;
        }

        $closedAt = $mesa->closed_at
            ?: ($e->closedAtIso ? Carbon::parse($e->closedAtIso)->utc() : now()->utc());

        // IDs de usuarios finales en esa mesa (ajustá filtro si tenés bajas, etc.)
        $userIds = Signup::query()
            ->where('game_table_id', $mesa->id)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        // Upsert por lote → idempotente y rápido
        $rows = $userIds->map(fn($uid) => [
            'user_id' => $uid,
            'game_table_id' => $mesa->id,
            'game_title' => (string) ($mesa->title ?? 'Mesa'),
            'closed_at' => $closedAt,
        ])->all();

        DB::table('vote_histories')->upsert(
            $rows,
            ['user_id', 'game_table_id'],      // constraint única
            ['game_title', 'closed_at']        // columnas a actualizar si ya existe
        );
    }
}
