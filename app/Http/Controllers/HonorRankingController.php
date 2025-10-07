<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Ranking de honor
 * - Usa sumas por subconsulta (sin ventanas, compatible con MySQL del hosting).
 * - Soporta instalaciones parciales: si no hay honor_events, usa users.honor.
 * - Rate-limit y ETag (304) para aliviar el servidor.
 */
final class HonorRankingController extends Controller
{
    public function __invoke(Request $request): HttpResponse
    {
        // ===== Rate-limit seguro (p. ej., 120 req/min por fingerprint) =====
        if ($resp = $this->enforceRateLimit($request, 'ranking:honor:index', 120, 60, 'auto')) {
            return $resp; // 429 con headers
        }

        $perPage = $this->perPage($request, 50, 100);
        $q = trim((string) $request->query('q', ''));
        $page = max(1, $request->integer('page', 1));
        $like = '%' . addcslashes($q, "%_\\") . '%';

        // ===== Estado de esquema =====
        $hasUsers = Schema::hasTable('users');
        $hasHonorEvents = $hasUsers && Schema::hasTable('honor_events');
        $hasUsersHonorColumn = $hasUsers && Schema::hasColumn('users', 'honor');

        // ===== ETag del “estado de datos” antes de hacer el listado =====
        $maxHe = $hasHonorEvents
            ? (string) (DB::table('honor_events')->max('updated_at')
                ?? DB::table('honor_events')->max('created_at') ?? '')
            : '';
        $maxU = $hasUsers
            ? (string) (DB::table('users')->max('updated_at')
                ?? DB::table('users')->max('created_at') ?? '')
            : '';

        $seed = ['etag_he' => $maxHe, 'etag_u' => $maxU, 'q' => $q, 'page' => $page, 'per' => $perPage];
        $etag = $this->makeEtag($seed);

        if ($notMod = $this->maybeNotModified($request, $etag, 60)) {
            // Menos CPU si el cliente ya tiene la versión fresca
            return $notMod; // 304
        }

        // ===== Fallback si faltan tablas/columnas =====
        if (!$hasUsers || (!$hasHonorEvents && !$hasUsersHonorColumn)) {
            $empty = new LengthAwarePaginator(collect(), 0, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            $response = response()->view('ranking.index', [
                'users' => $empty,
                'q' => $q,
                'myRank' => null,
            ]);

            return $this->withCacheHeaders($response, $request, $etag, 60);
        }

        // ===== Query principal (sin N+1) =====
        $usersQuery = User::query()->from('users AS u');
        $selects = ['u.*'];

        if ($hasHonorEvents) {
            $totalsSub = DB::table('honor_events')
                ->select('user_id', DB::raw('SUM(points) AS honor_total'))
                ->groupBy('user_id');

            $usersQuery->leftJoinSub($totalsSub, 't', 't.user_id', '=', 'u.id');
            $honorSelect = $hasUsersHonorColumn
                ? 'COALESCE(t.honor_total, u.honor, 0) AS honor_total'
                : 'COALESCE(t.honor_total, 0) AS honor_total';
        } else {
            $honorSelect = 'COALESCE(u.honor, 0) AS honor_total';
        }

        $selects[] = DB::raw($honorSelect);

        $users = $usersQuery
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    $w->where('u.name', 'LIKE', $like)
                        ->orWhere('u.username', 'LIKE', $like)
                        ->orWhere('u.email', 'LIKE', $like);
                });
            })
            ->select($selects)
            ->orderByDesc('honor_total')
            ->orderBy('u.id') // desempate estable
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends($request->query());

        // ===== Mi ranking (sin ventanas; compatible) =====
        $auth = $request->user();
        $myRank = null;

        if ($auth) {
            if ($hasHonorEvents) {
                $myTotal = (int) DB::table('honor_events')->where('user_id', $auth->id)->sum('points');

                // posición = 1 + cantidad de usuarios con total > mi total
                $betterCount = DB::table('honor_events')
                    ->select('user_id', DB::raw('SUM(points) AS s'))
                    ->groupBy('user_id')
                    ->havingRaw('s > ?', [$myTotal])
                    ->count();
            } else {
                $myTotal = (int) DB::table('users')->where('id', $auth->id)->value('honor');
                $betterCount = DB::table('users')->where('honor', '>', $myTotal)->count();
            }
            $myRank = $betterCount + 1;
        }

        $response = response()->view('ranking.index', compact('users', 'q', 'myRank'));
        return $this->withCacheHeaders($response, $request, $etag, 60);
    }
}
