{{-- resources/views/mesas/show.blade.php --}}
@extends('layouts.app')

@section('title', e($mesa->title) . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        /* ================= Mesa · Show (tema La Taberna) ================= */
        .show-wrap {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(1rem, 3vw, 1.4rem)
        }

        @media (min-width:900px) {
            .show-wrap {
                grid-template-columns: 2fr 1fr
            }
        }

        .card-pad {
            padding: clamp(.9rem, 2.4vw, 1.2rem)
        }

        .head-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .65rem
        }

        .title {
            margin: 0;
            font-size: clamp(1.25rem, 2.2vw, 1.6rem);
            font-weight: 800;
            color: #111827
        }

        @media (prefers-color-scheme:dark) {
            .title {
                color: var(--ink)
            }
        }

        .meta-chips {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border: 1px solid var(--line);
            border-radius: .5rem;
            padding: .18rem .6rem;
            font-size: .82rem;
            background: #fff;
            color: #111827
        }

        @media (prefers-color-scheme:dark) {
            .chip {
                background: #0f172a;
                color: var(--ink)
            }
        }

        .chip.ok {
            background: linear-gradient(180deg, rgba(16, 185, 129, .18), rgba(16, 185, 129, .10));
            border-color: rgba(16, 185, 129, .40)
        }

        .chip.err {
            background: linear-gradient(180deg, rgba(239, 68, 68, .16), rgba(239, 68, 68, .10));
            border-color: rgba(239, 68, 68, .40)
        }

        .cover {
            width: 100%;
            height: 240px;
            border-radius: .5rem;
            object-fit: cover;
            border: 1px solid var(--line);
            background: #fff;
            box-shadow: var(--shadow-sm)
        }

        .prose {
            line-height: 1.7;
            color: #111827
        }

        @media (prefers-color-scheme:dark) {
            .prose {
                color: var(--ink)
            }
        }

        .muted {
            color: var(--muted)
        }

        .actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            margin: .65rem 0
        }

        .btn.red {
            background: linear-gradient(135deg, #d66b6b, #b93838);
            border-color: transparent;
            color: #fff7ee;
            box-shadow: 0 12px 28px rgba(182, 56, 56, .28)
        }

        .btn.green {
            background: linear-gradient(135deg, #63b588, #2f855a);
            border-color: transparent;
            color: #0f2d1d;
            box-shadow: 0 12px 26px rgba(47, 133, 90, .28)
        }

        .grid-lists {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            margin-top: .6rem
        }

        .list-col {
            display: grid;
            gap: .35rem
        }

        .row {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .55rem 0;
            border-bottom: 1px solid var(--line)
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: .4rem;
            object-fit: cover;
            border: 1px solid var(--line);
            display: block;
            background: #fff
        }

        .kv {
            display: flex;
            gap: .5rem;
            align-items: center
        }

        .kv b {
            font-weight: 700;
            color: #111827
        }

        @media (prefers-color-scheme:dark) {
            .kv b {
                color: var(--ink)
            }
        }

        .pill {
            border: 1px solid var(--line);
            border-radius: .5rem;
            padding: .1rem .45rem;
            font-size: .78rem;
            color: var(--muted)
        }

        .aside-block {
            display: grid;
            gap: .48rem;
            margin-top: .35rem
        }

        .note-box {
            white-space: pre-wrap;
            line-height: 1.6;
            margin: .3rem 0 0
        }
    </style>
@endpush

@section('content')
    @php
        use Illuminate\Support\Facades\Route as LRoute;

        $img = $mesa->image_url_resolved ?? null;
        $isOpenNow = (bool) ($mesa->is_open_now ?? false);
        $isOpenFlag = (bool) ($mesa->is_open ?? false);
        $capacity = (int) ($mesa->capacity ?? 0);
        $occPercent = (int) ($mesa->occupancy_percent ?? 0);
        $joinUrl = $mesa->join_url ?? null;

        $editUrl = LRoute::has('mesas.edit') ? route('mesas.edit', $mesa) : null;
        $closeUrl = LRoute::has('mesas.close') ? route('mesas.close', $mesa) : null;
        $openUrl = LRoute::has('mesas.open') ? route('mesas.open', $mesa) : null;
        $notesUrl = LRoute::has('mesas.notes') ? route('mesas.notes', $mesa) : null;
        $signupStoreUrl = LRoute::has('signups.store') ? route('signups.store', $mesa) : null;
        $signupDelUrl = LRoute::has('signups.destroy') ? route('signups.destroy', $mesa) : null;

        $tz = config('app.display_timezone', config('app.timezone', 'UTC'));
        $opensAt = $mesa->opens_at ? $mesa->opens_at->timezone($tz) : null;
    @endphp

    <div class="show-wrap">
        {{-- Columna principal --}}
        <section class="card card-pad">
            <header class="head-row">
                <h1 class="title">{{ e($mesa->title) }}</h1>
                <div class="meta-chips">
                    <span class="chip {{ $isOpenNow ? 'ok' : 'err' }}">
                        {{ $isOpenNow ? __('Abierta') : __('Cerrada') }}
                    </span>
                    <span class="chip">{{ __('Capacidad: :n', ['n' => $capacity]) }}</span>
                    <span class="chip">{{ __('Ocupación: :p%', ['p' => $occPercent]) }}</span>
                </div>
            </header>

            @if($img)
                <img src="{{ $img }}"
                     alt="{{ __('Imagen de :t', ['t' => e($mesa->title)]) }}"
                     class="cover"
                     loading="lazy"
                     decoding="async">
            @endif

            @if($mesa->description)
                <p class="prose">{{ $mesa->description }}</p>
            @else
                <p class="muted">{{ __('Sin descripción.') }}</p>
            @endif

            @if($joinUrl)
                <p class="actions">
                    <a class="btn green"
                       href="{{ $joinUrl }}"
                       target="_blank"
                       rel="nofollow noopener">🚪 {{ __('Unirse / Link externo') }}</a>
                </p>
            @endif

            {{-- Gestión rápida --}}
            <div class="actions">
                @can('manage-tables')
                    @if($editUrl)
                        <a class="btn"
                           href="{{ $editUrl }}">✏️ {{ __('Editar') }}</a>
                    @endif

                    @if($isOpenFlag)
                        @if($closeUrl)
                            <form method="post"
                                  action="{{ $closeUrl }}">
                                @csrf
                                <button class="btn red"
                                        type="submit">🔒 {{ __('Cerrar') }}</button>
                            </form>
                        @endif
                    @else
                        @if($openUrl)
                            <form method="post"
                                  action="{{ $openUrl }}">
                                @csrf
                                <button class="btn green"
                                        type="submit">🔓 {{ __('Abrir') }}</button>
                            </form>
                        @endif
                    @endif

                    @if($notesUrl)
                        <a class="btn"
                           href="{{ $notesUrl }}">🗒️ {{ __('Notas') }}</a>
                    @endif
                @endcan
            </div>

            {{-- Listas --}}
            <div class="grid-lists">
                <section>
                    <h2 class="title"
                        style="font-size:1.05rem">
                        {{ __('🎲 Jugadores (:p/:c)', ['p' => $players->count(), 'c' => $capacity]) }}</h2>
                    <div class="list-col">
                        @forelse($players as $s)
                            <article class="row">
                                <img class="avatar"
                                     src="{{ $s->user_avatar_url }}"
                                     alt="avatar"
                                     loading="lazy"
                                     decoding="async">
                                <div style="min-width:0">
                                    <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        {{ $s->user_display_name ?? __('Usuario') }}</div>
                                    <div class="muted"
                                         style="font-size:.82rem">{{ $s->created_ago ?? '' }}</div>
                                </div>
                                @if((bool) ($s->is_manager ?? false))
                                    <span class="pill">{{ __('Encargado') }}</span>
                                @endif
                            </article>
                        @empty
                            <p class="muted">{{ __('No hay jugadores por ahora.') }}</p>
                        @endforelse
                    </div>
                </section>

                <section>
                    <h2 class="title"
                        style="font-size:1.05rem">{{ __('🕒 Lista de espera (:n)', ['n' => $waitlist->count()]) }}</h2>
                    <div class="list-col">
                        @forelse($waitlist as $s)
                            <article class="row">
                                <img class="avatar"
                                     src="{{ $s->user_avatar_url }}"
                                     alt="avatar"
                                     loading="lazy"
                                     decoding="async">
                                <div style="min-width:0">
                                    <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        {{ $s->user_display_name ?? __('Usuario') }}</div>
                                    <div class="muted"
                                         style="font-size:.82rem">{{ $s->created_ago ?? '' }}</div>
                                </div>
                            </article>
                        @empty
                            <p class="muted">{{ __('Sin lista de espera.') }}</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </section>

        {{-- Columna lateral --}}
        <aside class="card card-pad"
               aria-labelledby="det-title">
            <h3 id="det-title"
                class="title"
                style="font-size:1.05rem">{{ __('Detalles') }}</h3>

            <div class="aside-block">
                <div class="kv"><b>{{ __('Estado:') }}</b>
                    <span>{{ $isOpenNow ? __('Abierta ahora') : ($isOpenFlag ? __('Abre más tarde') : __('Cerrada')) }}</span>
                </div>

                @if($opensAt)
                    <div class="kv"><b>{{ __('Abre:') }}</b>
                        <span title="{{ $opensAt->toDayDateTimeString() }}">{{ $opensAt->format('Y-m-d H:i') }}</span>
                    </div>
                @endif

                <div class="kv"><b>{{ __('Encargado:') }}</b>
                    @if($mesa->manager)
                        <span>{{ $mesa->manager->name ?? $mesa->manager->username ?? $mesa->manager->email ?? '—' }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </div>

                <div class="kv"><b>{{ __('Creador:') }}</b>
                    @if($mesa->creator)
                        <span>{{ $mesa->creator->name ?? $mesa->creator->username ?? $mesa->creator->email ?? '—' }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </div>

                <div class="kv"><b>{{ __('Encargado cuenta como jugador:') }}</b>
                    <span>{{ $managerCountsAsPlayer ? __('Sí') : __('No') }}</span>
                </div>

                <div class="kv"><b>{{ __('Inscripciones:') }}</b>
                    <span>{{ (int) ($mesa->seats_taken ?? 0) }} / {{ $capacity }}</span>
                </div>

                @if($canViewNotes && filled($mesa->manager_note))
                    <div style="margin-top:.4rem">
                        <h4 class="title"
                            style="font-size:1rem">{{ __('Nota del encargado') }}</h4>
                        <p class="note-box">{!! nl2br(e($mesa->manager_note)) !!}</p>
                    </div>
                @endif
            </div>

            {{-- Acciones del usuario --}}
            <div style="margin-top:1rem">
                @auth
                    @if($alreadySigned && $signupDelUrl)
                        <form method="post"
                              action="{{ $signupDelUrl }}">
                            @csrf @method('DELETE')
                            <button class="btn red"
                                    type="submit">❌ {{ __('Retirarme') }}</button>
                        </form>
                    @elseif(!$alreadySigned && $signupStoreUrl)
                        <form method="post"
                              action="{{ $signupStoreUrl }}">
                            @csrf
                            <button class="btn green"
                                    type="submit"
                                    @disabled(($mesa->is_full ?? false) && !$isOpenNow)>
                                ✅ {{ __('Anotarme') }}
                            </button>
                        </form>
                    @endif
                @else
                    <p class="muted"
                       style="font-size:.92rem">{{ __('Iniciá sesión para anotarte.') }}</p>
                @endauth
            </div>
        </aside>
    </div>
@endsection