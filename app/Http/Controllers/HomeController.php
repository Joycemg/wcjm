<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;
use Illuminate\Support\Str;

/**
 * Landing / home
 * - Si el user está logueado, trae su última mesa (consulta mínima).
 * - Usa scopeSelectIsOpenNow() si existe; si no, calcula localmente.
 * - Sin N+1: sólo campos necesarios y counts en BD.
 */
final class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $auth = $this->optionalUser($request); // ?User

        /** @var GameTable|null $myMesa */
        $myMesa = null;

        // ===== Última mesa del usuario (si hay signups) =====
        if ($auth && Schema::hasTable('signups')) {
            $lastSignupMesaId = Signup::query()
                ->where('user_id', $auth->id)
                ->latest('id')
                ->value('game_table_id'); // int|null

            if ($lastSignupMesaId) {
                $query = GameTable::query()
                    ->select([
                        'id',
                        'title',
                        'description',
                        'capacity',
                        'image_path',
                        'image_url',
                        'is_open',
                        'opens_at',
                        'created_at',
                        'updated_at',
                        'manager_id',
                        'manager_counts_as_player',
                        'created_by',
                    ])
                    ->withCount([
                        'signups as signups_count' => fn($q) => $q->where('is_counted', 1),
                    ])
                    ->when(
                        method_exists(GameTable::class, 'scopeSelectIsOpenNow'),
                        fn($qb) => $qb->selectIsOpenNow()
                    )
                    ->when(
                        method_exists(GameTable::class, 'recentSignups'),
                        fn($qb) => $qb->with([
                            'recentSignups' => fn($q) => $q->where('is_counted', 1)->with([
                                'user:id,username,name,email,avatar_path,updated_at',
                            ]),
                        ])
                    );

                $myMesa = $query->find($lastSignupMesaId);

                if ($myMesa instanceof GameTable) {
                    // Si no vino del scope, calcular localmente
                    if ($myMesa->getAttribute('is_open_now') === null) {
                        $myMesa->setAttribute('is_open_now', $this->computeIsOpenNow($myMesa));
                    }
                }

                if (config('app.debug')) {
                    Log::debug('HomeController: última mesa del usuario', [
                        'user_id' => $auth->id,
                        'mesaId_from_sig' => $lastSignupMesaId,
                        'found_mesa' => $myMesa?->id,
                        'is_open_now' => (bool) $myMesa?->getAttribute('is_open_now'),
                        'signups_count' => $myMesa?->signups_count,
                    ]);
                }
            }
        }

        // Si existen parciales de tarjeta, la vista se encarga; si no, damos fallback
        $hasMesaCardPartial = ViewFacade::exists('mesas._card') || ViewFacade::exists('tables._card');
        $latestTables = $hasMesaCardPartial ? collect() : $this->latestTablesFallback();

        // ===== Contexto de permisos para la card =====
        $myMesaContext = [
            'notesUrl' => null,
            'canSeeNotes' => false,
            'isManager' => false,
            'isOwner' => false,
        ];

        if ($auth && $myMesa) {
            $isManager = (int) $myMesa->manager_id === (int) $auth->id;
            $isOwner = (int) $myMesa->created_by === (int) $auth->id;
            $isAdmin = ($auth->role ?? null) === 'admin';
            $isParticipant = true; // viene de tener un signup

            $myMesaContext['isManager'] = $isManager;
            $myMesaContext['isOwner'] = $isOwner;
            $myMesaContext['canSeeNotes'] = Route::has('mesas.notes') && ($isManager || $isOwner || $isAdmin || $isParticipant);
            if ($myMesaContext['canSeeNotes']) {
                $myMesaContext['notesUrl'] = route('mesas.notes', $myMesa);
            }
        }

        return view('home', [
            'myMesa' => $myMesa,
            'hasMesaCardPartial' => $hasMesaCardPartial,
            'latestTables' => $latestTables,
            'myMesaContext' => $myMesaContext,
        ]);
    }

    /** Cálculo local de is_open_now (fallback). */
    private function computeIsOpenNow(GameTable $mesa): bool
    {
        if (!$mesa->is_open)
            return false;

        $raw = $mesa->getAttribute('opens_at');
        if ($raw === null)
            return true;

        $tz = $this->tz();
        $openAt = $raw instanceof CarbonInterface
            ? Carbon::instance($raw)->timezone($tz)
            : Carbon::parse((string) $raw, $tz);

        return $this->nowTz()->greaterThanOrEqualTo($openAt);
    }

    /**
     * Fallback simple de “mesas recientes” cuando no existen parciales custom.
     * Usa cache corto para aligerar el home en hosting compartido.
     */
    private function latestTablesFallback(): Collection
    {
        if (!Schema::hasTable('game_tables')) {
            return collect();
        }

        $limit = max(0, (int) config('mesas.home_latest_limit', 4));
        if ($limit === 0)
            return collect();

        $fetch = function () use ($limit): array {
            $query = GameTable::query()
                ->select(['id', 'title', 'description', 'capacity', 'image_path', 'image_url', 'is_open', 'opens_at', 'created_at', 'updated_at'])
                ->withCount(['signups as signups_count' => fn($q) => $q->where('is_counted', 1)])
                ->orderByDesc('is_open')
                ->orderByDesc('created_at')
                ->limit($limit);

            if (method_exists(GameTable::class, 'scopeSelectIsOpenNow')) {
                $query->selectIsOpenNow()->orderByDesc('is_open_now');
            }

            return $query->get()
                ->map(fn(GameTable $m) => $this->formatMesaForHome($m))
                ->all();
        };

        $ttl = max(0, (int) config('mesas.home_latest_cache_seconds', 180));
        $cacheKey = 'home.latest_tables:v2:' . $limit;

        $data = $ttl > 0 ? Cache::remember($cacheKey, $ttl, $fetch) : $fetch();

        return collect($data);
    }

    /** Empaqueta la mesa con campos ya listos para la vista. */
    private function formatMesaForHome(GameTable $mesa): array
    {
        $tz = $this->tz();

        $opensAt = $mesa->opens_at instanceof CarbonInterface ? $mesa->opens_at->timezone($tz) : null;
        $isOpenNowAttr = $mesa->getAttribute('is_open_now');
        $isOpenNow = $isOpenNowAttr === null ? $this->computeIsOpenNow($mesa) : (bool) $isOpenNowAttr;

        $capacity = max(0, (int) $mesa->capacity);
        $signed = (int) ($mesa->signups_count ?? 0);

        // Si tu modelo expone accessor image_url_resolved, úsalo; si no, caé a image_url o placeholder:
        $image = $mesa->getAttribute('image_url_resolved') ?? ($mesa->image_url ?: null);

        return [
            'id' => (int) $mesa->id,
            'title' => (string) $mesa->title,
            'excerpt' => Str::limit((string) ($mesa->description ?? ''), 160),
            'image' => $image,
            'url' => $this->mesaShowUrl((int) $mesa->id),
            'players_label' => sprintf('%d/%d jugadores', $signed, $capacity),
            'signed' => $signed,
            'capacity' => $capacity,
            'status_label' => $isOpenNow ? 'Abierta' : 'Cerrada',
            'status_class' => $isOpenNow ? 'ok' : 'off',
            'is_open_now' => $isOpenNow,
            'opens_at_human' => $opensAt ? $opensAt->diffForHumans(null, ['parts' => 2]) : null,
            'opens_at_title' => $opensAt ? $opensAt->toDayDateTimeString() : null,
            'opens_at_iso' => $opensAt ? $opensAt->toIso8601String() : null,
            'updated_human' => $mesa->updated_at instanceof CarbonInterface
                ? $mesa->updated_at->timezone($tz)->diffForHumans(['parts' => 1, 'short' => true])
                : null,
        ];
    }

    private function mesaShowUrl(int $mesaId): string
    {
        return Route::has('mesas.show') ? route('mesas.show', $mesaId) : url('/mesas/' . $mesaId);
    }
}
