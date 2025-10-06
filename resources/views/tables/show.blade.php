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

        .pill.manager {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .2rem .6rem;
            border-radius: 999px;
            font-size: .8rem;
            border: 1px solid var(--border);
            background: #f3f4f6;
            color: #374151
        }

        .status-pill.ok {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #065f46
        }

        .status-pill.bad {
            background: #fee2e2;
            border-color: #fecaca;
            color: #991b1b
        }

        .status-pill.pending {
            background: #f3f4f6;
            border-color: #e5e7eb;
            color: #374151
        }

        .status-pill.neutral {
            background: #e0e7ff;
            border-color: #c7d2fe;
            color: #312e81
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

        .manager-grid {
            display: grid;
            gap: .75rem;
            margin-top: 1rem
        }

        .manager-block {
            display: flex;
            align-items: center;
            gap: .75rem
        }

        .manager-ava {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border);
            background: #f9fafb
        }

        .manager-meta {
            display: flex;
            flex-direction: column;
            gap: .1rem
        }

        .honor-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: flex-end
        }

        .honor-actions label {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            font-size: .9rem
        }

        .honor-actions select {
            min-width: 160px;
            padding: .35rem .45rem;
            border-radius: .4rem;
            border: 1px solid var(--border);
            background: var(--card);
            color: inherit
        }

        .honor-note {
            font-size: .85rem;
            color: var(--muted);
            margin-top: .35rem
        }

        .honor-form-row td {
            background: rgba(249, 250, 251, .6)
        }
    </style>
@endpush

@section('content')
    @php
        $tz = config('app.display_timezone', config('app.timezone'));
        $isOpenNow = (bool) $mesa->is_open_now;
        $signedOther = isset($myMesaId) && $myMesaId && $myMesaId !== $mesa->id;

        // REV lógico inicial (updated_at : state : opens_ts)
        $revTs = $mesa->updated_at?->timestamp ?? 0;
        $opensTs = $mesa->opens_at?->timestamp ?? 0;
        $state = $isOpenNow ? 1 : 0;
        $logicalRev = "{$revTs}:{$state}:{$opensTs}";

        $canVoteRoute = \Illuminate\Support\Facades\Route::has('signups.store');
        $canUnvoteRoute = \Illuminate\Support\Facades\Route::has('signups.destroy');
        $canOpenRoute = \Illuminate\Support\Facades\Route::has('mesas.open');
        $canCloseRoute = \Illuminate\Support\Facades\Route::has('mesas.close');
        $canEditRoute = \Illuminate\Support\Facades\Route::has('mesas.edit');
        $canDestroyRoute = \Illuminate\Support\Facades\Route::has('mesas.destroy');

        $isOwner = $isOwner ?? false;
        $isManager = $isManager ?? false;
        $isAdmin = $isAdmin ?? false;

        if (!$isAdmin) {
            $u = auth()->user();
            $isAdmin = $u && (
                (method_exists($u, 'can') && $u->can('admin')) ||
                (isset($u->role) && $u->role === 'admin') ||
                (isset($u->is_admin) && (bool) $u->is_admin)
            );
        }

        $canManageHonor = $canManageHonor ?? ($isOwner || $isManager || $isAdmin);

        $manager = $mesa->manager;
        $creator = $mesa->creator;
        $defaultAvatar = asset(config('auth.avatars.default', 'images/avatar-default.svg'));
        $managerAvatar = $manager?->avatar_url ?? $defaultAvatar;
        $managerVer = optional($manager?->updated_at)->timestamp;
        if ($managerAvatar && $managerVer) {
            $managerAvatar .= (\Illuminate\Support\Str::contains($managerAvatar, '?') ? '&' : '?') . 'v=' . $managerVer;
        }

        $ownerName = $creator?->name ?: ($creator?->username ?: __('Usuario') . ' #' . $mesa->created_by);
        $ownerProfileUrl = $creator ? route('profile.show', $creator->profile_param ?? $creator) : null;

        $managerName = $manager?->name ?: ($manager?->username ?: ($manager ? __('Usuario') . ' #' . $manager->id : null));
        $managerProfileUrl = $manager ? route('profile.show', $manager->profile_param ?? $manager) : null;
        $joinUrl = $mesa->join_url;
        $managerNote = $mesa->manager_note;
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

                <div class="manager-grid">
                    <div class="manager-block">
                        <img class="manager-ava"
                             src="{{ $managerAvatar }}"
                             alt="{{ $managerName ? __('Avatar de :name', ['name' => $managerName]) : __('Avatar por defecto') }}"
                             width="48"
                             height="48"
                             loading="lazy"
                             decoding="async"
                             onerror="this.onerror=null;this.src='{{ $defaultAvatar }}'">
                        <div class="manager-meta">
                            <strong>{{ __('Encargado') }}</strong>
                            @if($manager && $managerProfileUrl)
                                <a href="{{ $managerProfileUrl }}">{{ $managerName }}</a>
                            @elseif($managerName)
                                <span>{{ $managerName }}</span>
                            @else
                                <span class="muted">{{ __('Sin asignar') }}</span>
                            @endif
                            @if($manager && $creator && $manager->id === $creator->id)
                                <span class="pill manager">{{ __('También creador de la mesa') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="manager-meta">
                        <strong>{{ __('Creador') }}</strong>
                        @if($ownerProfileUrl)
                            <a href="{{ $ownerProfileUrl }}">{{ $ownerName }}</a>
                        @else
                            <span>{{ $ownerName }}</span>
                        @endif
                    </div>

                    @if($joinUrl)
                        <div class="manager-meta">
                            <strong>{{ __('Enlace de la mesa') }}</strong>
                            <a href="{{ $joinUrl }}"
                               target="_blank"
                               rel="noopener">{{ \Illuminate\Support\Str::limit($joinUrl, 70) }}</a>
                        </div>
                    @endif

                    <p class="honor-note">
                        💡 {{ __('Quien administra la mesa puede marcar asistencia, ausencias y comportamiento: el honor se actualiza en forma automática.') }}
                    </p>

                    @if($canManageHonor && filled($managerNote))
                        <div class="honor-note">
                            <strong>{{ __('Nota interna') }}:</strong>
                            {!! nl2br(e($managerNote)) !!}
                        </div>
                    @endif
                </div>

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
                                        <th scope="col">{{ __('Asistencia') }}</th>
                                        <th scope="col">{{ __('Comportamiento') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($players as $i => $s)
                                        @php
    $uRow = $s->relationLoaded('user') ? $s->user : null;
    $name = $uRow?->name ?? $uRow?->username ?? __('Usuario');
    $avatar = $s->user_avatar_url ?? null;
    $dt = $s->created_at?->timezone($tz);
    $attendedStatus = match (true) {
        $s->attended === true => ['label' => __('Asistió'), 'class' => 'ok'],
        $s->attended === false => ['label' => __('No asistió'), 'class' => 'bad'],
        default => ['label' => __('Pendiente'), 'class' => 'pending'],
    };
    $behaviorRaw = $s->behavior ?? 'regular';
    $behaviorStatus = match ($behaviorRaw) {
        'good' => ['label' => __('Buen comportamiento'), 'class' => 'ok'],
        'bad' => ['label' => __('Mal comportamiento'), 'class' => 'bad'],
        default => ['label' => __('Regular'), 'class' => 'neutral'],
    };
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
                                                    @if($s->is_manager)
                                                        <span class="pill manager">{{ __('Encargado') }}</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td>{{ $dt ? $dt->isoFormat('YYYY-MM-DD HH:mm:ss') : '—' }}</td>
                                            <td>
                                                <span class="status-pill {{ $attendedStatus['class'] }}">{{ $attendedStatus['label'] }}</span>
                                            </td>
                                            <td>
                                                <span class="status-pill {{ $behaviorStatus['class'] }}">{{ $behaviorStatus['label'] }}</span>
                                            </td>
                                        </tr>
                                        @if($canManageHonor)
                                            <tr class="honor-form-row">
                                                <td colspan="5">
                                                    <form method="POST"
                                                          action="{{ route('mesas.signups.attendance', [$mesa, $s]) }}">
                                                        @csrf
                                                        <div class="honor-actions">
                                                            <label>
                                                                {{ __('Asistencia') }}
                                                                <select name="attended">
                                                                    <option value="">{{ __('Sin cambios') }}</option>
                                                                    <option value="1" @selected($s->attended === true)>{{ __('Confirmar asistencia (+10)') }}</option>
                                                                    <option value="0" @selected($s->attended === false)>{{ __('Marcar como ausente') }}</option>
                                                                </select>
                                                            </label>
                                                            <label>
                                                                {{ __('No show') }}
                                                                <select name="no_show">
                                                                    <option value="0" selected>{{ __('No aplicar') }}</option>
                                                                    <option value="1">{{ __('Marcar No Show (-20)') }}</option>
                                                                </select>
                                                            </label>
                                                            <label>
                                                                {{ __('Comportamiento') }}
                                                                <select name="behavior">
                                                                    <option value="">{{ __('Sin cambios') }}</option>
                                                                    <option value="good" @selected($s->behavior === 'good')>{{ __('Buen comportamiento (+10)') }}</option>
                                                                    <option value="regular" @selected(($s->behavior ?? 'regular') === 'regular')>{{ __('Regular (0)') }}</option>
                                                                    <option value="bad" @selected($s->behavior === 'bad')>{{ __('Mal comportamiento (-10)') }}</option>
                                                                </select>
                                                            </label>
                                                            <button class="btn ok"
                                                                    type="submit">{{ __('Aplicar cambios') }}</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="5"
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
                                        <th scope="col">{{ __('Asistencia') }}</th>
                                        <th scope="col">{{ __('Comportamiento') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($waitlist as $i => $s)
                                        @php
    $uRow = $s->relationLoaded('user') ? $s->user : null;
    $name = $uRow?->name ?? $uRow?->username ?? __('Usuario');
    $avatar = $s->user_avatar_url ?? null;
    $dt = $s->created_at?->timezone($tz);
    $attendedStatus = match (true) {
        $s->attended === true => ['label' => __('Asistió'), 'class' => 'ok'],
        $s->attended === false => ['label' => __('No asistió'), 'class' => 'bad'],
        default => ['label' => __('Pendiente'), 'class' => 'pending'],
    };
    $behaviorRaw = $s->behavior ?? 'regular';
    $behaviorStatus = match ($behaviorRaw) {
        'good' => ['label' => __('Buen comportamiento'), 'class' => 'ok'],
        'bad' => ['label' => __('Mal comportamiento'), 'class' => 'bad'],
        default => ['label' => __('Regular'), 'class' => 'neutral'],
    };
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
                                            <td>
                                                <span class="status-pill {{ $attendedStatus['class'] }}">{{ $attendedStatus['label'] }}</span>
                                            </td>
                                            <td>
                                                <span class="status-pill {{ $behaviorStatus['class'] }}">{{ $behaviorStatus['label'] }}</span>
                                            </td>
                                        </tr>
                                        @if($canManageHonor)
                                            <tr class="honor-form-row">
                                                <td colspan="5">
                                                    <form method="POST"
                                                          action="{{ route('mesas.signups.attendance', [$mesa, $s]) }}">
                                                        @csrf
                                                        <div class="honor-actions">
                                                            <label>
                                                                {{ __('Asistencia') }}
                                                                <select name="attended">
                                                                    <option value="">{{ __('Sin cambios') }}</option>
                                                                    <option value="1" @selected($s->attended === true)>{{ __('Confirmar asistencia (+10)') }}</option>
                                                                    <option value="0" @selected($s->attended === false)>{{ __('Marcar como ausente') }}</option>
                                                                </select>
                                                            </label>
                                                            <label>
                                                                {{ __('No show') }}
                                                                <select name="no_show">
                                                                    <option value="0" selected>{{ __('No aplicar') }}</option>
                                                                    <option value="1">{{ __('Marcar No Show (-20)') }}</option>
                                                                </select>
                                                            </label>
                                                            <label>
                                                                {{ __('Comportamiento') }}
                                                                <select name="behavior">
                                                                    <option value="">{{ __('Sin cambios') }}</option>
                                                                    <option value="good" @selected($s->behavior === 'good')>{{ __('Buen comportamiento (+10)') }}</option>
                                                                    <option value="regular" @selected(($s->behavior ?? 'regular') === 'regular')>{{ __('Regular (0)') }}</option>
                                                                    <option value="bad" @selected($s->behavior === 'bad')>{{ __('Mal comportamiento (-10)') }}</option>
                                                                </select>
                                                            </label>
                                                            <button class="btn ok"
                                                                    type="submit">{{ __('Aplicar cambios') }}</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="5"
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
                                      action="{{ route('signups.store', $mesa) }}">
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
                                      action="{{ route('signups.destroy', $mesa) }}">
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