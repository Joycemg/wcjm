{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', __('Panel') . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        :root {
            --border: #e5e7eb;
            --muted: #6b7280;
            --maroon: #7b2d26;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --border: #2d2f33;
                --muted: #a7b0ba;
            }
        }

        .dash-wrap {
            max-width: 1000px;
            margin-inline: auto;
            padding: .75rem
        }

        .card-pad {
            padding: .75rem
        }

        .dash-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            flex-wrap: wrap
        }

        .dash-id {
            display: flex;
            align-items: center;
            gap: .5rem
        }

        .ava {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            border: 1px solid var(--border);
            object-fit: cover;
            flex: 0 0 32px
        }

        .dash-title {
            margin: 0;
            font-size: 1rem;
            line-height: 1.1;
            color: var(--maroon)
        }

        .honor {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .15rem .6rem;
            border-radius: 999px;
            font-size: .8rem;
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb
        }

        @media (prefers-color-scheme: dark) {
            .honor {
                background: #1a1c1f;
                color: #d1d5db;
                border-color: #2d2f33
            }
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: .5rem 0
        }

        .muted {
            color: var(--muted)
        }

        .list {
            margin: 0;
            padding-left: 1rem
        }

        .list li {
            margin: .2rem 0
        }

        .list .title {
            font-weight: 600
        }

        .list .date {
            color: var(--muted)
        }
    </style>
@endpush

@section('content')
    @php
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        $defaultAvatar = asset(config('auth.avatars.default', 'images/avatar-default.svg'));
        $baseAvatar = data_get($user, 'avatar_url', $defaultAvatar);
        $ver = optional($user?->updated_at)->timestamp;
        $avatar = $baseAvatar . ($ver ? ('?v=' . $ver) : '');

        $stats = $stats ?? [];
        $history = $history ?? [];

        $honor = data_get($stats, 'honor', data_get($user, 'honor'));
        // Normalizamos y tomamos hasta 30, ordenando por 'last' desc si existe
        $historyItems = \Illuminate\Support\Collection::wrap($history)
            ->when(
                fn($c) => $c->first() && (is_array($c->first()) || $c->first() instanceof ArrayAccess),
                fn($c) => $c->sortByDesc(fn($it) => data_get($it, 'last'))
            )
            ->take(30);
    @endphp

    <section class="card dash-wrap card-pad">
        <div class="dash-head">
            <div class="dash-id">
                <img class="ava"
                     src="{{ $avatar }}"
                     alt="{{ __('Avatar') }}"
                     loading="lazy"
                     decoding="async"
                     width="32"
                     height="32">
                <h1 class="dash-title">{{ __('Panel') }}</h1>
            </div>

            <div class="honor"
                 title="{{ __('Puntos de honor') }}">
                <svg width="14"
                     height="14"
                     viewBox="0 0 24 24"
                     aria-hidden="true">
                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21z"
                          fill="currentColor" />
                </svg>
                <span>{{ __('Honor:') }}
                    <strong>{{ is_numeric($honor) ? number_format((float) $honor, 0, ',', '.') : '—' }}</strong>
                </span>
            </div>
        </div>

        <div class="divider"
             role="separator"
             aria-hidden="true"></div>

        <h2 style="margin:0 0 .35rem;font-size:1.05rem">{{ __('Historial de mesas votadas') }}</h2>

        @if($historyItems->isEmpty())
            <p class="muted"
               style="margin:.25rem 0">{{ __('Todavía no votaste en ninguna mesa.') }}</p>
            @php
                $mesasIndexUrl = \Illuminate\Support\Facades\Route::has('mesas.index') ? route('mesas.index') : url('/mesas');
            @endphp
            <p style="margin:.25rem 0">
                <a class="btn"
                   href="{{ $mesasIndexUrl }}">{{ __('Explorar mesas') }}</a>
            </p>
        @else
            <ol class="list">
                @foreach($historyItems as $item)
                    @php
                        $mesa = data_get($item, 'mesa');
                        $mesaId = data_get($mesa, 'id');
                        $title = data_get($mesa, 'title') ?: data_get($item, 'title_fallback', __('Mesa'));
                        $raw = data_get($item, 'last');
                        try {
                            $dt = $raw ? \Illuminate\Support\Carbon::parse($raw) : null;
                        } catch (\Throwable $e) {
                            $dt = null;
                        }
                        $fecha = $dt?->isoFormat('LLL') ?? $dt?->toDayDateTimeString();
                        $hasShow = $mesaId && \Illuminate\Support\Facades\Route::has('mesas.show');
                    @endphp
                    <li>
                        @if($hasShow)
                            <a class="title"
                               href="{{ route('mesas.show', ['mesa' => $mesaId]) }}">{{ $title }}</a>
                        @else
                            <span class="title">{{ $title }}</span>
                        @endif
                        @if($fecha)<span class="date"> — {{ $fecha }}</span>@endif
                    </li>
                @endforeach
            </ol>

            @php
                $historyTotal = (is_countable($history) ? count($history) : $historyItems->count());
            @endphp
            @if($historyItems->count() < $historyTotal)
                <p class="muted"
                   style="margin:.4rem 0 0;font-size:.85rem">
                    {{ __('Mostrando las :n votaciones más recientes.', ['n' => 30]) }}
                </p>
            @endif
        @endif
    </section>

    @if(config('app.debug'))
        <aside class="dash-wrap muted"
               style="font-size:.9rem">
            <strong>DEBUG:</strong>
            user_id={{ auth()->id() }},
            history_count={{ is_countable($history) ? count($history) : $historyItems->count() }}
        </aside>
    @endif
@endsection