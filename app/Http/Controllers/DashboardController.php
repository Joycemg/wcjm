<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Requiere middleware 'auth' en la ruta, pero por las dudas:
        $user = $request->user();
        $honorEnabled = config('features.honor.enabled', false);
        $honorFromUser = $honorEnabled ? data_get($user, 'honor') : null;
        $stats = [
            // Mantiene compatibilidad si tu User tiene accessor/attr 'honor'
            'honor' => $honorEnabled && is_numeric($honorFromUser) ? (int) $honorFromUser : null,
            // Sumatoria real desde honor_events (si existe la tabla)
            'honor_total' => null,
        ];
        $history = [];

        if (!$user) {
            return view('dashboard', compact('history', 'stats'));
        }

        // =============== Config / límites ===============
        $limitCfg = (int) config('taberna.dashboard_history_limit', 30);
        $limit = max(1, min($limitCfg, 100));

        // =============== Memo schema checks (por request) ===============
        static $hasVoteHistories = null;
        static $hasGameTables = null;
        static $hasHonorEvents = null;
        static $colsVH = null;
        static $hasUsersHonorColumn = null;

        if ($hasVoteHistories === null)
            $hasVoteHistories = Schema::hasTable('vote_histories');
        if ($hasGameTables === null)
            $hasGameTables = Schema::hasTable('game_tables');
        if ($hasHonorEvents === null)
            $hasHonorEvents = Schema::hasTable('honor_events');
        if ($hasUsersHonorColumn === null)
            $hasUsersHonorColumn = Schema::hasTable('users') && Schema::hasColumn('users', 'honor');

        if ($hasVoteHistories && $colsVH === null) {
            $colsVH = [
                'kind' => Schema::hasColumn('vote_histories', 'kind'),
                'happened_at' => Schema::hasColumn('vote_histories', 'happened_at'),
                'closed_at' => Schema::hasColumn('vote_histories', 'closed_at'),
                'game_title' => Schema::hasColumn('vote_histories', 'game_title'),
                'game_table_id' => Schema::hasColumn('vote_histories', 'game_table_id'),
            ];
        } elseif ($colsVH === null) {
            $colsVH = ['kind' => false, 'happened_at' => false, 'closed_at' => false, 'game_title' => false, 'game_table_id' => false];
        }

        // =============== Honor total (si hay tabla) ===============
        if ($honorEnabled && $hasUsersHonorColumn) {
            $storedHonor = (int) DB::table('users')
                ->where('id', $user->id)
                ->value('honor');

            $stats['honor_persisted'] = $storedHonor;

            if ($stats['honor'] === null) {
                $stats['honor'] = $storedHonor;
            }
        }

        if ($honorEnabled && $hasHonorEvents) {
            $honorTotal = (int) DB::table('honor_events')
                ->where('user_id', $user->id)
                ->sum('points');

            $stats['honor_total'] = $honorTotal;
            $stats['honor'] = $honorTotal;
        } elseif ($honorEnabled && $stats['honor'] === null && $hasUsersHonorColumn) {
            // Instalaciones que persisten el agregado directo en users.honor.
            $stats['honor'] = $stats['honor_persisted'] ?? 0;
            $stats['honor_total'] = $stats['honor'];
        }

        // =============== Historia (si hay tabla) ===============
        if (!$hasVoteHistories) {
            return view('dashboard', compact('history', 'stats'));
        }

        // Columna temporal preferida
        $eventCol = $colsVH['happened_at'] ? 'vh.happened_at'
            : ($colsVH['closed_at'] ? 'vh.closed_at' : 'vh.created_at');

        // Query base
        $q = DB::table('vote_histories as vh')->where('vh.user_id', $user->id);

        // Filtramos por tipo solo si existe 'kind'
        if ($colsVH['kind']) {
            $q->where('vh.kind', 'close');
        }

        // Join a game_tables solo si existe
        if ($hasGameTables) {
            $q->leftJoin('game_tables as gt', 'gt.id', '=', 'vh.game_table_id');
        }

        // Select mínimo compatible
        // - mesa_id: si hay join, el id de gt; si no, el de vh (si existe); si no, null
        // - mesa_title: COALESCE(gt.title, vh.game_title) cuando aplique
        $selects = [
            DB::raw($eventCol . ' as event_time'),
        ];

        if ($hasGameTables) {
            $selects[] = DB::raw('gt.id as mesa_id');
            $selects[] = DB::raw(($colsVH['game_title'] ? 'COALESCE(gt.title, vh.game_title)' : 'gt.title') . ' as mesa_title');
        } else {
            // Sin join: intentamos proveer algo de contexto desde vote_histories
            if ($colsVH['game_table_id']) {
                $selects[] = DB::raw('vh.game_table_id as mesa_id');
            } else {
                $selects[] = DB::raw('NULL as mesa_id');
            }
            if ($colsVH['game_title']) {
                $selects[] = DB::raw('vh.game_title as mesa_title');
            } else {
                $selects[] = DB::raw('NULL as mesa_title');
            }
        }

        $rows = $q->select($selects)
            ->orderByDesc($eventCol)
            ->limit($limit)
            ->get();

        // Adaptar a la vista (tipado básico)
        $history = $rows->map(static function ($r) {
            $mesa = null;
            if (!is_null($r->mesa_id)) {
                $mesa = (object) [
                    'id' => (int) $r->mesa_id,
                    'title' => $r->mesa_title,
                ];
            }

            return [
                'mesa' => $mesa,
                'title_fallback' => $r->mesa_title ?? null, // si no hubo join, puede venir de vh.game_title
                'last' => $r->event_time,         // string/DateTime según driver
            ];
        })->all();

        return view('dashboard', compact('history', 'stats'));
    }
}
