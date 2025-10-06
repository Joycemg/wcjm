{{-- resources/views/mesas/show.blade.php --}}
@extends('layouts.app')

@section('title', e($mesa->title) . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        :root {
            --muted: #6b7280;
            --maroon: #7b2d26;
            --border: #e5e7eb;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --muted: #a7b0ba;
                --border: #2d2f33;
            }
        }

        .muted {
            color: var(--muted)
        }

        .pill {
            display: inline-block;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: .15rem .6rem;
            font-size: .85rem
        }

        .pill.ok {
            background: #e7f8f1;
            color: #065f46;
            border-color: #a7e6cf
        }

        .pill.off {
            background: #f3f4f6;
            color: #374151
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: .75rem 0
        }

        .ava {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            object-fit: cover;
            border: 1px solid var(--border)
        }

        .table-wrap {
            overflow: auto
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            padding: .45rem .5rem;
            border-bottom: 1px solid var(--border);
            text-align: left
        }

        .stack {
            display: flex;
            flex-direction: column
        }

        .grid {
            display: grid;
            gap: .75rem
        }

        .g2 {
            grid-template-columns: 2fr 1fr
        }

        @media (max-width:860px) {
            .g2 {
                grid-template-columns: 1fr
            }
        }
    </style>
@endpush

@section('content')
    @php
use Illuminate\Support\Carbon;

$tz = config('app.display_timezone', config('app.timezone'));
$isOpenNow = (bool) $mesa->is_open_now;
$signedOther = isset($myMesaId) && $myMesaId && $myMesaId !== $mesa->id;

// REV lógico inicial (updated_at : state : opens_ts)
$revTs = $mesa->updated_at?->timestamp ?? 0;
$opensTs = $mesa->opens_at?->timestamp ?? 0;
$state = $isOpenNow ? 1 : 0;
$logicalRev = "{$revTs}:{$state}:{$opensTs}";

$canVoteRoute = \Illuminate\Support\Facades\Route::has('mesas.vote');
$canUnvoteRoute = \Illuminate\Support\Facades\Route::has('mesas.unvote');
$canOpenRoute = \Illuminate\Support\Facades\Route::has('mesas.open');
$canCloseRoute = \Illuminate\Support\Facades\Route::has('mesas.close');
$canEditRoute = \Illuminate\Support\Facades\Route::has('mesas.edit');
$canDestroyRoute = \Illuminate\Support\Facades\Route::has('mesas.destroy');

// Admin (tolerante a distintas implementaciones)
$u = auth()->user();
$isAdmin = $u && (
    (method_exists($u, 'can') && $u->can('admin')) ||
    (isset($u->role) && $u->role === 'admin') ||
    (isset($u->is_admin) && (bool) $u->is_admin)
);
    @endphp

    {{-- Wrapper con datos para auto-actualizar justo en opens_at --}}
    <div id="mesa-page"
         data-mesa-id="{{ $mesa->id }}"
         data-is-open="{{ $mesa->is_open ? 1 : 0 }}"
         data-opens-at="{{ $mesa->opens_at ? $mesa->opens_at->toIso8601String() : '' }}"
         data-rev="">

        <div class="grid g2">
            <div class="card"
                 style="padding:1rem">
                <header class="stack"
                        style="gap:.25rem">
                    <h2 style="color:var(--maroon);margin:0">{{ $mesa->title }}</h2>

                    <p class="muted"
                       style="margin:0"
                       aria-live="polite">
                        {{ __('Capacidad') }}:
                        <strong>{{ (int) $mesa->capacity }}</strong>
                        ·
                        {{ __('Estado') }}:
                        <strong class="{{ $isOpenNow ? 'text-ok' : 'text-off' }}"
                                style="color:{{ $isOpenNow ? 'var(--maroon)' : 'var(--muted)' }}">
                            {{ $isOpenNow ? __('Abierta') : __('Cerrada') }}
                        </strong>
                    </p>

                    @if($mesa->opens_at)
                        <p class="muted"
                           style="margin:0">
                            {{ __('Apertura programada') }}:
                            {{ $mesa->opens_at->timezone($tz)->isoFormat('YYYY-MM-DD HH:mm') }}
                        </p>
                    @endif
                </header>

                @if(filled($mesa->description))
                    <div class="prose"
                         style="margin-top:.75rem">
                        {{ $mesa->description }}
                    </div>
                @endif

                <div class="divider"></div>

                <div class="grid"
                     style="gap:1rem">
                    {{-- Jugadores --}}
                    <section class="card"
                             id="jugadores"
                             style="padding:1rem">
                        <h3 style="color:var(--maroon);margin-top:0">
                            {{ __('Jugadores') }} ({{ $players->count() }}/{{ (int) $mesa->capacity }})
                        </h3>

                        <div class="table-wrap">
                            <table>
                                <caption class="sr-only">{{ __('Listado de jugadores') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">{{ __('Usuario') }}</th>
                                        <th scope="col">{{ __('Fecha voto') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($players as $i => $s)
                                        @php
    $uRow = $s->relationLoaded('user') ? $s->user : null;
    $name = $uRow?->name ?? $uRow?->username ?? __('Usuario');
    $avatar = $s->user_avatar_url ?? null;
    $dt = $s->created_at?->timezone($tz);
                                        @endphp
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <span style="display:flex;align-items:center;gap:.5rem">
                                                    @if($avatar)
                                                        <img class="ava"
                                                             src="{{ $avatar }}"
                                                             alt="{{ $name }}"
                                                             width="24"
                                                             height="24"
                                                             loading="lazy"
                                                             decoding="async">
                                                    @endif
                                                    <span>{{ $name }}</span>
                                                </span>
                                            </td>
                                            <td>{{ $dt ? $dt->isoFormat('YYYY-MM-DD HH:mm:ss') : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3"
                                                class="muted">{{ __('Sin votos aún.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- Reserva --}}
                    <section class="card"
                             id="reserva"
                             style="padding:1rem">
                        <h3 style="color:var(--maroon);margin-top:0">
                            {{ __('Reserva (lista de espera)') }} ({{ $waitlist->count() }})
                        </h3>

                        <div class="table-wrap">
                            <table>
                                <caption class="sr-only">{{ __('Listado de espera') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">{{ __('Usuario') }}</th>
                                        <th scope="col">{{ __('Fecha voto') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($waitlist as $i => $s)
                                        @php
    $uRow = $s->relationLoaded('user') ? $s->user : null;
    $name = $uRow?->name ?? $uRow?->username ?? __('Usuario');
    $avatar = $s->user_avatar_url ?? null;
    $dt = $s->created_at?->timezone($tz);
                                        @endphp
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <span style="display:flex;align-items:center;gap:.5rem">
                                                    @if($avatar)
                                                        <img class="ava"
                                                             src="{{ $avatar }}"
                                                             alt="{{ $name }}"
                                                             width="24"
                                                             height="24"
                                                             loading="lazy"
                                                             decoding="async">
                                                    @endif
                                                    <span>{{ $name }}</span>
                                                </span>
                                            </td>
                                            <td>{{ $dt ? $dt->isoFormat('YYYY-MM-DD HH:mm:ss') : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3"
                                                class="muted">{{ __('Vacía') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            <aside class="card"
                   style="padding:1rem">
                <h3 style="color:var(--maroon);margin-top:0">{{ __('Acciones') }}</h3>

                @auth
                    @if($isOpenNow)
                        @if(!$alreadySigned)
                            @if ($canVoteRoute)
                                <form method="POST"
                                      action="{{ route('mesas.vote', $mesa) }}">
                                    @csrf
                                    <button class="btn ok"
                                            style="width:100%"
                                            {{ $signedOther ? 'disabled' : '' }}
                                            aria-disabled="{{ $signedOther ? 'true' : 'false' }}"
                                            @if($signedOther)
                                                title="{{ __('Ya votaste en otra mesa') }}"
                                            @endif>
                                        🗳️ {{ __('Votar / Reservar') }}
                                    </button>
                                </form>
                            @endif
                            @if($signedOther)
                                <p class="muted"
                                   style="margin-top:.4rem">
                                    {{ __('Ya votaste en otra mesa. Retirá tu voto allí para votar aquí.') }}
                                </p>
                            @endif
                        @else
                            @if ($canUnvoteRoute)
                                <form method="POST"
                                      action="{{ route('mesas.unvote', $mesa) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger"
                                            style="width:100%">✖ {{ __('Retirar voto') }}</button>
                                </form>
                            @endif
                        @endif
                    @else
                        <p class="muted">{{ __('La mesa aún no está abierta para votar.') }}</p>
                    @endif
                @else
                    <p class="muted">{{ __('Iniciá sesión para votar.') }}</p>
                @endauth

                {{-- Admin --}}
                @if ($isAdmin)
                    <div class="divider"></div>
                    <h4 style="margin:.1rem 0 .5rem;color:#b08900">{{ __('Administrador') }}</h4>

                    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                        @if ($canOpenRoute)
                            <form method="POST"
                                  action="{{ route('mesas.open', $mesa) }}"
                                  style="display:inline">
                                @csrf
                                <button class="btn ok"
                                        {{ $isOpenNow ? 'disabled' : '' }}
                                        aria-disabled="{{ $isOpenNow ? 'true' : 'false' }}">
                                    {{ __('Abrir') }}
                                </button>
                            </form>
                        @endif

                        @if ($canCloseRoute)
                            <form method="POST"
                                  action="{{ route('mesas.close', $mesa) }}"
                                  style="display:inline">
                                @csrf
                                <button class="btn danger"
                                        {{ $isOpenNow ? '' : 'disabled' }}
                                        aria-disabled="{{ $isOpenNow ? 'false' : 'true' }}">
                                    {{ __('Cerrar') }}
                                </button>
                            </form>
                        @endif

                        @if ($canEditRoute)
                            <a class="btn"
                               href="{{ route('mesas.edit', $mesa) }}">✏️ {{ __('Editar') }}</a>
                        @endif

                        @if ($canDestroyRoute)
                            <form method="POST"
                                  action="{{ route('mesas.destroy', $mesa) }}"
                                  onsubmit="return confirm('{{ __('¿Eliminar mesa?') }}')"
                                  style="display:inline">
                                @csrf @method('DELETE')
                                <button class="btn danger">🗑️ {{ __('Eliminar') }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection

{{-- ⚠️ Asegurate de tener @stack('scripts') antes de </body> en layouts.app --}}
@push('scripts')
    <script>
        (function () {
            const el = document.getElementById('mesa-page');
            if (!el) return;

            const isOpen = +el.dataset.isOpen === 1;
            const opensISO = el.dataset.opensAt || '';
            const toMs = iso => iso ? new Date(iso).getTime() : null;
            const now = () => Date.now();

            const opensMs = toMs(opensISO);

            // 1) Reload preciso al llegar a opens_at si la mesa está "is_open"
            if (isOpen && opensMs && opensMs > now()) {
                const lead = Math.max(0, opensMs - now() + 300); // tolerancia
                setTimeout(() => window.location.reload(), lead);
                setTimeout(() => window.location.reload(), lead + 2000); // aftershock por suspensión de pestaña
            }

            // 2) Volver a foco: si ya pasó la hora, recargar
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && isOpen && opensMs && opensMs <= now()) {
                    window.location.reload();
                }
            });

            // 3) Respaldo ocasional (no es polling constante)
            const iv = setInterval(() => {
                if (isOpen && opensMs && opensMs <= now()) window.location.reload();
            }, 5 * 60 * 1000);
            window.addEventListener('beforeunload', () => clearInterval(iv));
        })();
    </script>
@endpush