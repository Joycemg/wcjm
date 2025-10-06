@extends('layouts.app')

@section('title', 'Ranking de Honor · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        .table {
            width: 100%;
            border-collapse: collapse
        }

        .table th,
        .table td {
            padding: .6rem;
            border-bottom: 1px solid var(--border, #e5e7eb)
        }

        .table tr.me {
            background: rgba(125, 211, 252, .15)
        }

        .search {
            display: flex;
            gap: .5rem;
            margin-bottom: 1rem
        }

        .pill {
            display: inline-block;
            padding: .1rem .5rem;
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 999px;
            font-size: .85rem
        }
    </style>
@endpush

@section('content')
    <div class="container">
        <h1>🏅 Ranking de Honor</h1>

        <form method="GET"
              class="search">
            <input type="text"
                   name="q"
                   value="{{ $q }}"
                   placeholder="Buscar por nombre/usuario/email"
                   class="input" />
            <button class="btn">Buscar</button>
        </form>

        @auth
            @if($myRank)
                <p>Tu posición: <span class="pill">#{{ $myRank }}</span></p>
            @endif
        @endauth

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th style="text-align:right">Honor total</th>
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
                            @if($u->username) <span class="pill">@{{ $u->username }}</span> @endif
                        </td>
                        <td style="text-align:right">{{ (int) $u->honor_total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:1rem">
            {{ $users->links() }}
        </div>
    </div>
@endsection