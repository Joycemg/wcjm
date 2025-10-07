{{-- resources/views/mesas/notes.blade.php --}}
@extends('layouts.app')

@section('title', __('Notas internas de :mesa', ['mesa' => $mesa->title]) . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        :root {
            --muted: #6b7280;
            --border: #e5e7eb;
            --maroon: #7b2d26
        }

        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 1rem;
            display: grid;
            gap: 1rem
        }

        .head {
            display: flex;
            flex-direction: column;
            gap: .5rem
        }

        .head h1 {
            margin: 0;
            color: var(--maroon)
        }

        .meta {
            color: var(--muted);
            font-size: .95rem
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: 2fr 1fr
        }

        @media (max-width:860px) {
            .grid {
                grid-template-columns: 1fr
            }
        }

        .card {
            padding: 1rem;
            border-radius: 0;
            border: 1px solid var(--border);
            background: #fff
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .1rem .45rem;
            border-radius: 0;
            border: 1px solid var(--border);
            font-size: .8rem;
            color: var(--muted);
            margin-top: .15rem;
            width: fit-content
        }

        .textarea {
            width: 100%;
            min-height: 200px;
            padding: .75rem;
            border-radius: 0;
            border: 1px solid var(--border);
            resize: vertical;
            font: inherit;
            line-height: 1.5
        }

        .list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: .6rem
        }

        .row {
            display: flex;
            align-items: center;
            gap: .6rem
        }

        .row img {
            width: 36px;
            height: 36px;
            border-radius: 0;
            object-fit: cover;
            border: 1px solid var(--border);
            display: block
        }

        .btn {
            display: inline-flex;
            gap: .5rem;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: 0;
            padding: .5rem .9rem
        }

        .btn.ok {
            background: #dcfce7;
            border-color: #22c55e
        }

        .link {
            color: var(--maroon);
            text-decoration: none
        }

        .link:hover {
            text-decoration: underline
        }
    </style>
@endpush

@section('content')
    <main class="wrap">
        <div class="head">
            <a class="link"
               href="{{ route('mesas.show', $mesa) }}">&larr; {{ __('Volver a la mesa') }}</a>
            <h1>{{ __('Notas internas de :mesa', ['mesa' => $mesa->title]) }}</h1>
            <p class="meta">
                {{ __('Estas notas solo son visibles para el encargado y las personas inscriptas en la mesa.') }}
            </p>
        </div>

        <div class="grid">
            <section class="card">
                <h2>{{ $canEdit ? __('Editar notas') : __('Notas compartidas') }}</h2>

                @if($canEdit)
                    <form method="POST"
                          action="{{ route('mesas.notes.update', $mesa) }}">
                        @csrf @method('PUT')
                        <label class="sr-only"
                               for="manager_note">{{ __('Notas de la mesa') }}</label>
                        <textarea id="manager_note"
                                  name="manager_note"
                                  class="textarea"
                                  placeholder="{{ __('Agregá recordatorios, pautas o enlaces útiles para tu mesa.') }}">{{ old('manager_note', $mesa->manager_note) }}</textarea>
                        @error('manager_note') <div class="text-danger"
                         style="margin-top:.35rem">{{ $message }}</div> @enderror
                        <div style="display:flex;justify-content:flex-end;margin-top:.75rem">
                            <button class="btn ok"
                                    type="submit">{{ __('Guardar notas') }}</button>
                        </div>
                    </form>
                @elseif(filled($mesa->manager_note))
                    <div style="white-space:pre-wrap;line-height:1.6">{!! nl2br(e($mesa->manager_note)) !!}</div>
                @else
                    <div style="color:var(--muted);background:rgba(123,45,38,.05);border-radius: 0;padding:.75rem">
                        {{ __('Todavía no hay notas cargadas.') }}
                    </div>
                @endif
            </section>

            <aside class="card">
                <h2>{{ __('Quiénes pueden ver esto') }}</h2>
                <ul class="list">
                    @foreach($players as $signup)
                        @php
                            $u = $signup->user;
                            $avatar = $signup->user_avatar_url;
                            $name = $signup->user_display_name ?? ($u?->name ?? $u?->username ?? __('Usuario'));
                          @endphp
                        <li class="row">
                            <img src="{{ $avatar }}"
                                 alt="{{ $name }}"
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