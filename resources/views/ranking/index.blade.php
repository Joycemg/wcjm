@extends('layouts.app')

@section('title', 'Ranking de Honor · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: .6rem;
            border-bottom: 1px solid var(--border, #e5e7eb);
        }

        .table tr.me {
            background: rgba(125, 211, 252, .15);
        }

        .search {
            display: flex;
            gap: .5rem;
            margin-bottom: 1rem;
        }

        .pill {
            display: inline-block;
            padding: .1rem .5rem;
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 0;
            font-size: .85rem;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .results-summary {
            margin: .5rem 0 1rem;
            color: var(--muted, #6b7280);
        }

        .empty-state {
            padding: 1.25rem;
            border: 1px dashed var(--border, #e5e7eb);
            border-radius: 0;
            background: rgba(243, 244, 246, .4);
            color: var(--muted, #6b7280);
        }
    </style>
@endpush

@section('content')
    <div class="container">
        <h1>🏅 Ranking de Honor</h1>

        <form method="GET"
              class="search">
            <label class="sr-only" for="search-q">Buscar por nombre, usuario o email</label>
            <input id="search-q"
                   type="text"
                   name="q"
                   value="{{ $q }}"
                   placeholder="Buscar por nombre/usuario/email"
                   class="input"
                   autocomplete="off"
                   spellcheck="false"
                   aria-describedby="results-summary"
                   aria-controls="ranking-table" />
            <button class="btn">Buscar</button>
        </form>

        @auth
            @if($myRank)
                <p>Tu posición: <span class="pill">#{{ $myRank }}</span></p>
            @endif
        @endauth

        @php $total = (int) $users->total(); @endphp

        <p id="results-summary"
           class="results-summary"
           role="status"
           aria-live="polite">
            @if($total === 0)
                No encontramos jugadores@if($q !== '') para la búsqueda “{{ $q }}”@endif.
            @elseif($total === 1)
                1 jugador encontrado@if($q !== '') para la búsqueda “{{ $q }}”@endif.
            @else
                {{ number_format($total, 0, ',', '.') }} jugadores encontrados@if($q !== '') para la búsqueda “{{ $q }}”@endif.
            @endif
        </p>

        @if($users->isEmpty())
            <p class="empty-state">Probá con otros términos o verificá la ortografía.</p>
        @else
            <table id="ranking-table" class="table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Usuario</th>
                        <th scope="col" style="text-align:right">Honor total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $offset = ($users->currentPage() - 1) * $users->perPage();
                    @endphp
                    @foreach($users as $i => $u)
                        <tr class="{{ auth()->id() === $u->id ? 'me' : '' }}">
                            <td>{{ $offset + $i + 1 }}</td>
                            <td>
                                <a href="{{ route('profile.show', $u) }}">{{ $u->name ?? 'Usuario ' . $u->id }}</a>
                                @if($u->username)
                                    <span class="pill">@{{ $u->username }}</span>
                                @endif
                            </td>
                            <td style="text-align:right">{{ number_format((int) $u->honor_total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div style="margin-top:1rem">
            {{ $users->links() }}
        </div>
    </div>
@endsection
