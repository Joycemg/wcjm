@extends('layouts.app')

@section('title', __('Mesas') . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        /* ===== Mesas · Index (consistente con base.css) ===== */
        .index-head {
            display: flex;
            align-items: baseline;
            gap: .6rem;
            flex-wrap: wrap
        }

        .index-title {
            margin: 0;
            color: var(--ink);
            font-weight: 800;
            letter-spacing: .01em
        }

        .muted {
            color: var(--muted)
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 0;
            padding: clamp(.9rem, 2.5vw, 1.15rem);
            box-shadow: var(--shadow-sm)
        }

        .cards {
            display: grid;
            gap: clamp(.75rem, 2.5vw, 1rem);
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            margin-top: 1rem
        }
    </style>
@endpush

@section('content')
    @php
        use Illuminate\Support\Facades\Route as LRoute;
        $total = method_exists($tables, 'total') ? $tables->total() : (is_countable($tables) ? count($tables) : null);
        $hasCreate = LRoute::has('mesas.create');
        $createUrl = $hasCreate ? route('mesas.create') : url('/mesas/create');
    @endphp

    <header class="index-head">
        <h1 class="index-title">{{ __('Mesas publicadas') }}</h1>
        @if(!is_null($total)) <small class="muted">({{ $total }})</small>@endif
    </header>

    <div class="cards"
         aria-live="polite">
        @forelse($tables as $mesa)
            @includeFirst(['mesas._card', 'tables._card'], ['mesa' => $mesa, 'myMesaId' => $myMesaId ?? null])
        @empty
            <div class="card">
                <p class="muted"
                   style="margin:.1rem 0">{{ __('No hay mesas aún.') }}</p>
                @auth
                    @if ($hasCreate)
                        <p style="margin:.5rem 0 0">
                            <a href="{{ $createUrl }}"
                               class="btn ok">➕ {{ __('Crear una mesa') }}</a>
                        </p>
                    @endif
                @endauth
            </div>
        @endforelse
    </div>

    @if(method_exists($tables, 'withQueryString'))
        <div style="margin-top:1rem">
            {{ $tables->withQueryString()->links() }}
        </div>
    @endif
@endsection