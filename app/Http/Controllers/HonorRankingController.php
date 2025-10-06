<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class HonorRankingController extends Controller
{
    public function __invoke(Request $request): HttpResponse
    {
        $perPage = $this->perPage($request, 50, 100);
        $q = trim((string) $request->query('q', ''));

        // FIX: evitar warning usando integer() y clamp a [1..]
        $page = max(1, $request->integer('page', 1));

        $like = '%' . addcslashes($q, "%_\\") . '%';

        // ===== Fallback si faltan tablas (primera instalación) =====
        if (!Schema::hasTable('users') || !Schema::hasTable('honor_events')) {
            $empty = new LengthAwarePaginator(
                collect(), // items
                0,         // total
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            // ETag básico para página vacía
            $etag = $this->makeEtag(['empty' => true, 'q' => $q, 'page' => $page, 'per' => $perPage]);
            if ($notMod = $this->maybeNotModified($request, $etag, 60)) {
                return $notMod; // 304
            }

            $response = response()->view('ranking.index', [
                'users' => $empty,
                'q' => $q,
                'myRank' => null,
            ]);
            return $this->withCacheHeaders($response, $request, $etag, 60);
        }

        // ===== Subconsulta de totales (SUM points por usuario) =====
        $totalsSub = DB::table('honor_events')
            ->select('user_id', DB::raw('SUM(points) AS honor_total'))
            ->groupBy('user_id');

        // ===== Query principal (JOIN a subconsulta; búsqueda segura) =====
        $users = User::query()
            ->from('users AS u')
            ->leftJoinSub($totalsSub, 't', 't.user_id', '=', 'u.id')
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    $w->where('u.name', 'LIKE', $like)
                        ->orWhere('u.username', 'LIKE', $like)
                        ->orWhere('u.email', 'LIKE', $like);
                });
            })
            ->select([
                'u.*',
                DB::raw('COALESCE(t.honor_total, 0) AS honor_total'),
            ])
            ->orderByDesc('honor_total')
            ->orderBy('u.id') // desempate estable
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends($request->query());

        // ===== Mi ranking (sin window functions; compatible con MySQL del hosting) =====
        $auth = $request->user();
        $myRank = null;

        if ($auth) {
            $myTotal = (int) DB::table('honor_events')->where('user_id', $auth->id)->sum('points');

            // posición = 1 + cantidad de usuarios con total > mi total
            $betterCount = DB::query()
                ->fromSub($totalsSub, 't')
                ->where('t.honor_total', '>', $myTotal)
                ->count();

            $myRank = $betterCount + 1;
        }

        // ===== ETag de la vista SSR (304 si no cambió) =====
        $maxHe = (string) (DB::table('honor_events')->max('updated_at')
            ?? DB::table('honor_events')->max('created_at') ?? '');
        $maxU = (string) (DB::table('users')->max('updated_at')
            ?? DB::table('users')->max('created_at') ?? '');

        $seed = [
            'rev_he' => $maxHe,
            'rev_u' => $maxU,
            'q' => $q,
            'page' => $page,
            'per_page' => $perPage,
        ];
        $etag = $this->makeEtag($seed);

        if ($notMod = $this->maybeNotModified($request, $etag, 60)) {
            return $notMod; // 304
        }

        // ===== Render y headers de cache =====
        $response = response()->view('ranking.index', compact('users', 'q', 'myRank'));
        return $this->withCacheHeaders($response, $request, $etag, 60);
    }
}
