{{-- resources/views/mesas/notes.blade.php --}}
@extends('layouts.app')

@section('title', __('Notas internas de :mesa', ['mesa' => $mesa->title]) . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        /* ====== Mesa · Notas ====== */
        .notes-wrap {
            max-width: 1000px;
            margin-inline: auto;
            display: grid;
            gap: 1rem;
        }

        .head {
            display: flex;
            flex-direction: column;
            gap: .4rem;
        }

        .head h1 {
            margin: 0;
            color: var(--maroon);
        }

        .meta {
            color: var(--muted);
            font-size: .95rem;
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: 2fr 1fr;
        }

        @media (max-width: 860px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: .5rem;
            padding: 1rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .1rem .45rem;
            border-radius: .5rem;
            border: 1px solid var(--line);
            font-size: .8rem;
            color: var(--muted);
            width: fit-content;
            margin-top: .15rem;
        }

        .textarea {
            width: 100%;
            min-height: 200px;
            padding: .75rem;
            border-radius: .5rem;
            border: 1px solid var(--line);
            resize: vertical;
            font: inherit;
            line-height: 1.5;
            background: var(--card);
            color: var(--ink);
        }

        .list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: .6rem;
        }

        .row {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .row img {
            width: 36px;
            height: 36px;
            border-radius: .4rem;
            object-fit: cover;
            border: 1px solid var(--line);
            display: block;
            background: #fff;
        }

        .btn.ok {
            background: rgba(16, 185, 129, .15);
            border-color: rgba(16, 185, 129, .4);
        }

        .link {
            color: var(--maroon);
            text-decoration: none;
        }

        .link:hover {
            text-decoration: underline;
        }
    </style>
@endpush

@section('content')
    @php
        use Illuminate\Support\Facades\Route as LRoute;
        $showUrl = LRoute::has('mesas.show') ? route('mesas.show', $mesa) : url('/mesas/' . ($mesa->id ?? ''));
        $updateUrl = LRoute::has('mesas.notes.update') ? route('mesas.notes.update', $mesa) : null;
    @endphp

    <main class="notes-wrap">
        <div class="head">
            <a class="link"
               href="{{ $showUrl }}">&larr; {{ __('Volver a la mesa') }}</a>
            <h1>{{ __('Notas internas de :mesa', ['mesa' => $mesa->title]) }}</h1>
            <p class="meta">
                {{ __('Estas notas solo son visibles para el encargado y las personas inscriptas en la mesa.') }}</p>
        </div>

        <div class="grid">
            <section class="card"
                     aria-labelledby="notes-title">
                <h2 id="notes-title"
                    style="margin:0 0 .5rem">{{ $canEdit ? __('Editar notas') : __('Notas compartidas') }}</h2>

                @if($canEdit && $updateUrl)
                    <form method="POST"
                          action="{{ $updateUrl }}">
                        @csrf @method('PUT')
                        <label class="sr-only"
                               for="manager_note">{{ __('Notas de la mesa') }}</label>
                        <textarea id="manager_note"
                                  name="manager_note"
                                  class="textarea"
                                  placeholder="{{ __('Agregá recordatorios, pautas o enlaces útiles para tu mesa.') }}">{{ old('manager_note', $mesa->manager_note) }}</textarea>
                        @error('manager_note')
                            <div style="color:#b91c1c;margin-top:.35rem">{{ $message }}</div>
                        @enderror
                        <div style="display:flex;justify-content:flex-end;margin-top:.75rem">
                            <button class="btn ok"
                                    type="submit">{{ __('Guardar notas') }}</button>
                        </div>
                    </form>
                @elseif(filled($mesa->manager_note))
                    <div style="white-space:pre-wrap;line-height:1.6">{!! nl2br(e($mesa->manager_note)) !!}</div>
                @else
                    <div class="muted"
                         style="background:rgba(123,45,38,.06);padding:.75rem;border-radius:.5rem">
                        {{ __('Todavía no hay notas cargadas.') }}
                    </div>
                @endif
            </section>

            <aside class="card"
                   aria-labelledby="who-title">
                <h2 id="who-title"
                    style="margin:0 0 .5rem">{{ __('Quiénes pueden ver esto') }}</h2>
                <ul class="list">
                    @foreach($players as $signup)
                        @php
                            $u = $signup->user;
                            $avatar = $signup->user_avatar_url;
                            $name = $signup->user_display_name ?? ($u?->name ?? $u?->username ?? __('Usuario'));
                          @endphp
                        <li class="row">
                            <img src="{{ $avatar }}"
                                 alt="{{ e($name) }}"
                                 width="36"
                                 height="36"
                                 loading="lazy"
                                 decoding="async">
                            <span style="display:flex;flex-direction:column">
                                <strong>{{ $name }}</strong>
                                @if($signup->is_manager)
                                    <span class="pill">{{ __('Encargado') }}</span>
                                @elseif($signup->is_player)
                                    <span class="pill">{{ __('Jugador/a') }}</span>
                                @else
                                    <span class="pill">{{ __('Lista de espera') }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </main>
@endsection