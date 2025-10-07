{{-- resources/views/mesas/show.blade.php --}}
@extends('layouts.app')

@section('title', e($mesa->title) . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        .cover {
            width: 100%;
            height: 220px;
            border-radius: 0;
            object-fit: cover;
            border: 1px solid var(--border);
            background: #fafafa
        }

        .kv {
            display: flex;
            gap: .5rem;
            align-items: center
        }

        .kv b {
            font-weight: 600
        }

        .chip {
            border: 1px solid var(--border);
            border-radius: 0;
            padding: .1rem .55rem;
            font-size: .75rem
        }
    </style>
@endpush

@section('content')
    <div class="grid-2">
        {{-- Columna principal --}}
        <section class="card">
            <div class="flex items-start justify-between gap-3 mb-3">
                <h1 class="text-2xl font-bold">{{ $mesa->title }}</h1>

                <div class="flex items-center gap-2">
                    @if($mesa->is_open_now)
                        <span class="chip bg-green-50">Abierta</span>
                    @else
                        <span class="chip bg-red-50">Cerrada</span>
                    @endif

                    <span class="chip">Capacidad: {{ $mesa->capacity }}</span>
                    <span class="chip">Ocupación: {{ $mesa->occupancy_percent }}%</span>
                </div>
            </div>

            @php
                $img = $mesa->image_url_resolved;
            @endphp
            @if($img)
                <img src="{{ $img }}"
                     alt="Imagen de {{ $mesa->title }}"
                     class="cover mb-3">
            @endif

            @if($mesa->description)
                <p class="prose max-w-none mb-4">{{ $mesa->description }}</p>
            @else
                <p class="muted mb-4">Sin descripción.</p>
            @endif

            @if(!empty($mesa->join_url))
                <p class="mb-4">
                    <a class="btn green"
                       href="{{ $mesa->join_url }}"
                       target="_blank"
                       rel="nofollow noopener">🚪 Unirse / Link externo</a>
                </p>
            @endif

            {{-- Gestión rápida (solo quien corresponde) --}}
            <div class="flex flex-wrap gap-2 mb-2">
                @can('manage-tables')
                    @if(Route::has('mesas.edit'))
                        <a class="btn"
                           href="{{ route('mesas.edit', $mesa) }}">✏️ Editar</a>
                    @endif

                    @if($mesa->is_open)
                        <form method="post"
                              action="{{ route('mesas.close', $mesa) }}">
                            @csrf
                            <button class="btn red"
                                    type="submit">🔒 Cerrar</button>
                        </form>
                    @else
                        <form method="post"
                              action="{{ route('mesas.open', $mesa) }}">
                            @csrf
                            <button class="btn green"
                                    type="submit">🔓 Abrir</button>
                        </form>
                    @endif

                    @if(Route::has('mesas.notes'))
                        <a class="btn"
                           href="{{ route('mesas.notes', $mesa) }}">🗒️ Notas</a>
                    @endif
                @endcan
            </div>

            {{-- Listado de jugadores y espera --}}
            <div class="grid gap-4 md:grid-cols-2 mt-4">
                <div>
                    <h2 class="font-semibold mb-2">🎲 Jugadores ({{ $players->count() }}/{{ $mesa->capacity }})</h2>
                    @forelse($players as $s)
                        <article class="flex items-center gap-3 py-2 border-b">
                            <img class="avatar"
                                 src="{{ $s->user_avatar_url }}"
                                 alt="avatar">
                            <div class="min-w-0">
                                <div class="truncate">{{ $s->user_display_name ?? 'Usuario' }}</div>
                                <div class="text-xs muted">{{ $s->created_ago ?? '' }}</div>
                            </div>
                            @if((bool) ($s->is_manager ?? false))
                                <span class="pill">Encargado</span>
                            @endif
                        </article>
                    @empty
                        <p class="muted">No hay jugadores por ahora.</p>
                    @endforelse
                </div>

                <div>
                    <h2 class="font-semibold mb-2">🕒 Lista de espera ({{ $waitlist->count() }})</h2>
                    @forelse($waitlist as $s)
                        <article class="flex items-center gap-3 py-2 border-b">
                            <img class="avatar"
                                 src="{{ $s->user_avatar_url }}"
                                 alt="avatar">
                            <div class="min-w-0">
                                <div class="truncate">{{ $s->user_display_name ?? 'Usuario' }}</div>
                                <div class="text-xs muted">{{ $s->created_ago ?? '' }}</div>
                            </div>
                        </article>
                    @empty
                        <p class="muted">Sin lista de espera.</p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Columna lateral --}}
        <aside class="card">
            <h3 class="font-semibold mb-3">Detalles</h3>

            <div class="space-y-2">
                <div class="kv"><b>Estado:</b>
                    <span>{{ $mesa->is_open_now ? 'Abierta ahora' : ($mesa->is_open ? 'Abre más tarde' : 'Cerrada') }}</span>
                </div>

                @if($mesa->opens_at)
                    <div class="kv"><b>Abre:</b>
                        <span>{{ $mesa->opens_at->timezone(config('app.display_timezone', config('app.timezone', 'UTC')))->format('Y-m-d H:i') }}</span>
                    </div>
                @endif

                <div class="kv"><b>Encargado:</b>
                    @if($mesa->manager)
                        <span>{{ $mesa->manager->name ?? $mesa->manager->username ?? $mesa->manager->email ?? '—' }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </div>

                <div class="kv"><b>Creador:</b>
                    @if($mesa->creator)
                        <span>{{ $mesa->creator->name ?? $mesa->creator->username ?? $mesa->creator->email ?? '—' }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </div>

                @if($managerCountsAsPlayer)
                    <div class="kv"><b>Encargado cuenta como jugador:</b> <span>Sí</span></div>
                @else
                    <div class="kv"><b>Encargado cuenta como jugador:</b> <span>No</span></div>
                @endif

                <div class="kv"><b>Inscripciones:</b>
                    <span>{{ $mesa->seats_taken }} / {{ $mesa->capacity }}</span>
                </div>

                @if($canViewNotes && !empty($mesa->manager_note))
                    <div class="mt-4">
                        <h4 class="font-semibold">Nota del encargado</h4>
                        <p class="mt-1">{{ $mesa->manager_note }}</p>
                    </div>
                @endif
            </div>

            {{-- Acciones del usuario (ejemplo) --}}
            <div class="mt-4">
                @auth
                    @if($alreadySigned)
                        <form method="post"
                              action="{{ route('signups.destroy', $mesa) }}">
                            @csrf @method('DELETE')
                            <button class="btn red"
                                    type="submit">❌ Retirarme</button>
                        </form>
                    @else
                        <form method="post"
                              action="{{ route('signups.store', $mesa) }}">
                            @csrf
                            <button class="btn green"
                                    type="submit"
                                    @disabled($mesa->is_full && !$mesa->is_open_now)>
                                ✅ Anotarme
                            </button>
                        </form>
                    @endif
                @else
                    <p class="muted text-sm">Iniciá sesión para anotarte.</p>
                @endauth
            </div>
        </aside>
    </div>
@endsection