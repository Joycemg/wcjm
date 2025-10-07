<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Dashboard con stats ligeros y un historial reciente del usuario.
 * - Evita N+1 (usa agregados en BD).
 * - Funciona aunque algunas tablas/columnas aún no existan (instalaciones parciales).
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $stats = [
            'honor' => null, // “visible” (preferencia: honor_events sum)
            'honor_total' => null, // suma real de honor_events (si existe)
            'honor_persisted' => null, // valor en users.honor (si existe)
        ];
        $history = [];

        if (!$user) {
            // Si no hay sesión, devolvemos un dashboard vacío.
            return view('dashboard', compact('history', 'stats'));
        }

        // ================= Config / límites =================
        $limitCfg = (int) config('taberna.dashboard_history_limit', 30);
        $limit = max(1, min($limitCfg, 100));

        // ================= Schema memo =================
        static $has = null;
        static $vhCols = null;

        if ($has === null) {
            $has = [
                'vote_histories' => Schema::hasTable('vote_histories'),
                'game_tables' => Schema::hasTable('game_tables'),
                'honor_events' => Schema::hasTable('honor_events'),
                'users' => Schema::hasTable('users'),
            ];
            $has['users.honor'] = $has['users'] && Schema::hasColumn('users', 'honor');
        }

        if ($has['vote_histories'] && $vhCols === null) {
            $vhCols = [
                'kind' => Schema::hasColumn('vote_histories', 'kind'),
                'happened_at' => Schema::hasColumn('vote_histories', 'happened_at'),
                'closed_at' => Schema::hasColumn('vote_histories', 'closed_at'),
                'game_title' => Schema::hasColumn('vote_histories', 'game_title'),
                'game_table_id' => Schema::hasColumn('vote_histories', 'game_table_id'),
            ];
        } elseif ($vhCols === null) {
            $vhCols = ['kind' => false, 'happened_at' => false, 'closed_at' => false, 'game_title' => false, 'game_table_id' => false];
        }

        // ================= Honor agregado (persistido) =================
        if ($has['users.honor']) {
            $stats['honor_persisted'] = (int) DB::table('users')
                ->where('id', $user->id)
                ->value('honor');
        }

        // ================= Honor total (eventos) =================
        if ($has['honor_events']) {
            $stats['honor_total'] = (int) DB::table('honor_events')
                ->where('user_id', $user->id)
                ->sum('points');

            $stats['honor'] = $stats['honor_total'];
        } else {
            // Fallback a la columna persistida si existe
            $stats['honor'] = $stats['honor_persisted'] ?? 0;
            $stats['honor_total'] = $stats['honor'];
        }

        // ================= Historial =================
        if ($has['vote_histories']) {
            // Columna temporal preferida (agnóstico al esquema)
            $eventCol = $vhCols['happened_at'] ? 'vh.happened_at'
                : ($vhCols['closed_at'] ? 'vh.closed_at' : 'vh.created_at');

            $q = DB::table('vote_histories as vh')
                ->where('vh.user_id', $user->id);

            if ($vhCols['kind']) {
                $q->where('vh.kind', 'close');
            }

            if ($has['game_tables']) {
                $q->leftJoin('game_tables as gt', 'gt.id', '=', 'vh.game_table_id');
            }

            $select = [DB::raw($eventCol . ' as event_time')];

            if ($has['game_tables']) {
                $select[] = DB::raw('gt.id as mesa_id');
                $select[] = DB::raw(($vhCols['game_title'] ? 'COALESCE(gt.title, vh.game_title)' : 'gt.title') . ' as mesa_title');
            } else {
                $select[] = DB::raw($vhCols['game_table_id'] ? 'vh.game_table_id as mesa_id' : 'NULL as mesa_id');
                $select[] = DB::raw($vhCols['game_title'] ? 'vh.game_title as mesa_title' : 'NULL as mesa_title');
            }

            $rows = $q->select($select)
                ->orderByDesc($eventCol)
                ->limit($limit)
                ->get();

            $history = $rows->map(static function ($r) {
                $mesa = null;
                if ($r->mesa_id !== null) {
                    $mesa = (object) [
                        'id' => (int) $r->mesa_id,
                        'title' => $r->mesa_title,
                    ];
                }

                return [
                    'mesa' => $mesa,
                    'title_fallback' => $r->mesa_title ?? null,
                    'last' => $r->event_time, // string/DateTime según driver
                ];
            })->all();
        }

        return view('dashboard', compact('history', 'stats'));
    }
}
