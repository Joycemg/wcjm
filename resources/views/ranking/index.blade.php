{{-- resources/views/ranking/honor.blade.php --}}
@extends('layouts.app')

@section('title', 'Ranking de Honor · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        /* ====== Ranking · Honor ====== */
        .rank-wrap {
            display: grid;
            gap: 1rem;
        }

        .search {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .input {
            width: min(420px, 100%);
            padding: .55rem .7rem;
            border: 1px solid var(--line);
            border-radius: .5rem;
            background: var(--card);
            color: var(--ink);
        }

        .input:focus-visible {
            outline: 3px solid #60a5fa;
            outline-offset: 2px;
        }

        .pill {
            display: inline-block;
            padding: .1rem .5rem;
            border: 1px solid var(--line);
            border-radius: .5rem;
            font-size: .85rem;
            background: var(--card);
            color: var(--ink);
        }

        .results-summary {
            margin: .25rem 0 0;
            color: var(--muted);
        }

        .table-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: .5rem;
            padding: 1rem;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: .6rem .6rem;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }

        .table th {
            text-align: left;
            font-weight: 700;
            color: var(--ink);
        }

        .num {
            text-align: right;
        }

        .table tr.me {
            background: rgba(125, 211, 252, .15);
        }

        .empty-state {
            padding: 1.25rem;
            border: 1px dashed var(--line);
            border-radius: .5rem;
            background: rgba(243, 244, 246, .4);
            color: var(--muted);
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
    </style>
@endpush

@section('content')
    @php
        use Illuminate\Support\Facades\Route as LRoute;
        $q = (string) ($q ?? request('q', ''));
        $total = method_exists($users, 'total') ? (int) $users->total() : (is_countable($users) ? count($users) : 0);
        $offset = method_exists($users, 'currentPage') ? (($users->currentPage() - 1) * $users->perPage()) : 0;
        $hasProfile = LRoute::has('profile.show');
    @endphp

    <section class="rank-wrap"
             aria-labelledby="rank-title">
        <header>
            <h1 id="rank-title"
                class="app-title"
                style="margin:0">🏅 {{ __('Ranking de Honor') }}</h1>
        </header>

        <form method="GET"
              class="search"
              role="search"
              aria-label="{{ __('Buscar jugadores') }}">
            <label class="sr-only"
                   for="search-q">{{ __('Buscar por nombre, usuario o email') }}</label>
            <input id="search-q"
                   type="text"
                   name="q"
                   value="{{ $q }}"
                   placeholder="{{ __('Buscar por nombre/usuario/email') }}"
                   class="input"
                   autocomplete="off"
                   spellcheck="false"
                   aria-describedby="results-summary"
                   aria-controls="ranking-table">
            <button class="btn"
                    type="submit">{{ __('Buscar') }}</button>
            @if($q !== '')
                <a class="btn"
                   href="{{ url()->current() }}">{{ __('Limpiar') }}</a>
            @endif
        </form>

        @auth
            @if(!empty($myRank))
                <p>{{ __('Tu posición:') }} <span class="pill">#{{ (int) $myRank }}</span></p>
            @endif
        @endauth

        <p id="results-summary"
           class="results-summary"
           role="status"
           aria-live="polite">
            @if($total === 0)
                {{ __('No encontramos jugadores') }}@if($q !== '') {{ __(' para la búsqueda “:q”', ['q' => $q]) }}@endif.
            @elseif($total === 1)
                {{ __('1 jugador encontrado') }}@if($q !== '') {{ __(' para la búsqueda “:q”', ['q' => $q]) }}@endif.
            @else
                {{ number_format($total, 0, ',', '.') }} {{ __('jugadores encontrados') }}@if($q !== '')
                {{ __(' para la búsqueda “:q”', ['q' => $q]) }}@endif.
            @endif
        </p>

        <div class="table-card">
            @if(collect($users)->isEmpty())
                <p class="empty-state">{{ __('Probá con otros términos o verificá la ortografía.') }}</p>
            @else
                <div class="table-wrap">
                    <table id="ranking-table"
                           class="table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">{{ __('Usuario') }}</th>
                                <th scope="col"
                                    class="num">{{ __('Honor total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $i => $u)
                                @php
                                    $isMe = auth()->id() === ($u->id ?? null);
                                    $display = $u->name ?? ('Usuario ' . $u->id);
                                    $profileUrl = $hasProfile ? route('profile.show', $u) : '#';
                                  @endphp
                                <tr class="{{ $isMe ? 'me' : '' }}">
                                    <td>{{ $offset + $i + 1 }}</td>
                                    <td>
                                        @if($hasProfile)
                                            <a href="{{ $profileUrl }}">{{ e($display) }}</a>
                                        @else
                                            {{ e($display) }}
                                        @endif
                                        @if(!empty($u->username))
                                            <span class="pill">@{{ $u->username }}</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ number_format((int) ($u->honor_total ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if(method_exists($users, 'withQueryString'))
                <div style="margin-top:1rem">
                    {{ $users->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection