<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\Signup;
use App\Models\User; // ← para tipar el auth user y evitar warnings
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Landing / home
     * - Si el user está logueado, busca su último signup y carga la mesa mínima.
     * - Usa scopeSelectIsOpenNow() si existe; si no, calcula is_open_now localmente.
     */
    public function __invoke(Request $request): View
    {
        $auth = $this->optionalUser($request); // ← ?User tipado

        /** @var GameTable|null $myMesa */
        $myMesa = null;

        if ($auth && Schema::hasTable('signups')) {
            // 1) Último signup → sólo el id de mesa (consulta liviana)
            $lastSignupMesaId = Signup::query()
                ->where('user_id', $auth->id)
                ->latest('id')
                ->value('game_table_id'); // int|null

            // 2) Cargar mesa si existe
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
                    ])
                    ->withCount([
                        // consistente con el resto: sólo signups contados
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

                // find() mantiene GameTable|null
                $myMesa = $query->find($lastSignupMesaId);

                // 3) Si NO vino is_open_now desde el scope, calcularlo localmente
                if ($myMesa instanceof GameTable) {
                    $isOpenNowAttr = $myMesa->getAttribute('is_open_now'); // bool|null
                    if ($isOpenNowAttr === null) {
                        $myMesa->setAttribute('is_open_now', $this->computeIsOpenNow($myMesa));
                    }
                }

                if (config('app.debug')) {
                    Log::debug('HomeController myMesa', [
                        'user_id' => $auth->id,
                        'mesaId_from_signup' => $lastSignupMesaId,
                        'found_mesa' => $myMesa?->id,
                        'is_open_now' => (bool) $myMesa?->getAttribute('is_open_now'),
                        'signups_count' => $myMesa?->signups_count,
                    ]);
                }
            }
        }

        // ¿Existe el partial de tarjeta?
        $hasMesaCardPartial = ViewFacade::exists('mesas._card') || ViewFacade::exists('tables._card');

        return view('home', [
            'myMesa' => $myMesa,
            'hasMesaCardPartial' => $hasMesaCardPartial,
        ]);
    }

    /**
     * Cálculo local de is_open_now (fallback si no hay scope).
     */
    private function computeIsOpenNow(GameTable $mesa): bool
    {
        if (!$mesa->is_open) {
            return false;
        }

        $opensRaw = $mesa->getAttribute('opens_at');
        if ($opensRaw === null) {
            return true;
        }

        $tz = $this->tz();
        $openAt = $opensRaw instanceof CarbonInterface
            ? Carbon::instance($opensRaw)->timezone($tz)
            : Carbon::parse((string) $opensRaw, $tz);

        return $this->nowTz()->greaterThanOrEqualTo($openAt);
    }

    /** Devuelve el usuario autenticado tipado o null (evita warning del IDE) */
    private function optionalUser(Request $request): ?User
    {
        $u = $request->user();
        return $u instanceof User ? $u : null;
    }
}
