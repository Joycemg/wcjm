{{-- resources/views/tables/notes.blade.php --}}
@extends('layouts.app')

@section('title', __('Notas internas de :mesa', ['mesa' => $mesa->title]) . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        :root {
            --muted: #6b7280;
            --border: #e5e7eb;
            --maroon: #7b2d26;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --muted: #a7b0ba;
                --border: #2d2f33;
            }
        }

        .notes-wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 1rem;
            display: grid;
            gap: 1rem;
        }

        .notes-header {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .notes-header h1 {
            margin: 0;
            color: var(--maroon);
        }

        .notes-meta {
            color: var(--muted);
            font-size: .95rem;
        }

        .notes-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: 2fr 1fr;
        }

        @media (max-width: 860px) {
            .notes-grid {
                grid-template-columns: 1fr;
            }
        }

        .notes-card {
            padding: 1rem;
            border-radius: .75rem;
            border: 1px solid var(--border);
            background: var(--card, #fff);
        }

        .notes-card h2 {
            margin-top: 0;
            color: var(--maroon);
        }

        .notes-textarea {
            width: 100%;
            min-height: 200px;
            padding: .75rem;
            border-radius: .6rem;
            border: 1px solid var(--border);
            resize: vertical;
            font: inherit;
            line-height: 1.5;
        }

        .notes-preview {
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .notes-empty {
            color: var(--muted);
            background: rgba(125, 45, 38, .05);
            border-radius: .6rem;
            padding: .75rem;
        }

        .notes-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: .6rem;
        }

        .notes-player {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .notes-player img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border);
        }

        .notes-player span {
            display: flex;
            flex-direction: column;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .1rem .45rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            font-size: .8rem;
            color: var(--muted);
            margin-top: .15rem;
            width: fit-content;
        }

        .notes-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: .75rem;
        }

        .notes-actions .btn {
            min-width: 160px;
        }
    </style>
@endpush

@section('content')
    <main class="notes-wrap">
        <div class="notes-header">
            <a class="link" href="{{ route('mesas.show', $mesa) }}">&larr; {{ __('Volver a la mesa') }}</a>
            <h1>{{ __('Notas internas de :mesa', ['mesa' => $mesa->title]) }}</h1>
            <p class="notes-meta">
                {{ __('Estas notas solo son visibles para el encargado y las personas inscriptas en la mesa.') }}
            </p>
        </div>

        <div class="notes-grid">
            <section class="notes-card">
                <h2>{{ $canEdit ? __('Editar notas') : __('Notas compartidas') }}</h2>

                @if($canEdit)
                    <form method="POST" action="{{ route('mesas.notes.update', $mesa) }}">
                        @csrf
                        @method('PUT')
                        <label class="sr-only" for="manager_note">{{ __('Notas de la mesa') }}</label>
                        <textarea id="manager_note"
                                  name="manager_note"
                                  class="notes-textarea"
                                  placeholder="{{ __('Agregá recordatorios, pautas o enlaces útiles para tu mesa.') }}">{{ old('manager_note', $mesa->manager_note) }}</textarea>
                        @error('manager_note')
                            <div class="text-danger" style="margin-top:.35rem">{{ $message }}</div>
                        @enderror
                        <div class="notes-actions">
                            <button class="btn ok" type="submit">{{ __('Guardar notas') }}</button>
                        </div>
                    </form>
                @elseif(filled($mesa->manager_note))
                    <div class="notes-preview">{!! nl2br(e($mesa->manager_note)) !!}</div>
                @else
                    <div class="notes-empty">{{ __('Todavía no hay notas cargadas.') }}</div>
                @endif
            </section>

            <aside class="notes-card">
                <h2>{{ __('Quiénes pueden ver esto') }}</h2>
                <ul class="notes-list">
                    @foreach($players as $signup)
                        @php
                            $u = $signup->user;
                            $avatar = $signup->user_avatar_url;
                            $name = $signup->user_display_name ?? ($u?->name ?? $u?->username ?? __('Usuario'));
                        @endphp
                        <li class="notes-player">
                            <img src="{{ $avatar }}"
                                 alt="{{ $name }}"
                                 loading="lazy"
                                 decoding="async">
                            <span>
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
