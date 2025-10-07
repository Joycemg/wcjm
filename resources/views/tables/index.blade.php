{{-- resources/views/mesas/index.blade.php --}}
@extends('layouts.app')

@section('title', __('Mesas') . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        :root {
            --muted: #6b7280;
            --maroon: #7b2d26;
            --border: #e5e7eb
        }

        .muted {
            color: var(--muted)
        }

        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 0;
            padding: 1rem
        }

        .cards {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr))
        }
    </style>
@endpush

@section('content')
    @php
        $total = method_exists($tables, 'total') ? $tables->total() : (is_countable($tables) ? count($tables) : null);
        $hasCreate = \Illuminate\Support\Facades\Route::has('mesas.create');
    @endphp

    <header>
        <h1 style="color:var(--maroon);margin:0">
            {{ __('Mesas publicadas') }} @if(!is_null($total)) <small class="muted">({{ $total }})</small>@endif
        </h1>
    </header>

    <div class="cards"
         style="margin-top:1rem">
        @forelse($tables as $mesa)
            @includeFirst(['mesas._card', 'tables._card'], ['mesa' => $mesa, 'myMesaId' => $myMesaId ?? null])
        @empty
            <div class="card">
                <p class="muted"
                   style="margin:.1rem 0">{{ __('No hay mesas aún.') }}</p>
                @auth
                    @if ($hasCreate)
                        <p style="margin:.5rem 0 0">
                            <a href="{{ route('mesas.create') }}"
                               class="btn">{{ __('Crear una mesa') }}</a>
                        </p>
                    @endif
                @endauth
            </div>
        @endforelse
    </div>

    <div style="margin-top:1rem">
        @if(method_exists($tables, 'withQueryString'))
            {{ $tables->withQueryString()->links() }}
        @endif
    </div>
@endsection