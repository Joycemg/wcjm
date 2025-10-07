{{-- resources/views/home.blade.php --}}
@extends('layouts.app')

@section('title', config('app.name', 'La Taberna').' · Rol & Juegos')

@push('head')
<meta name="description" content="Organizá partidas, descubrí mesas abiertas y sumate a la comunidad.">
<style>
/* ====== Home (scoped) ====== */
:root{
  --muted:#6b7280; --border:#e5e7eb; --maroon:#7b2d26;
}
.home-wrap{max-width:960px;margin-inline:auto;padding:1rem}
.card-pad{padding:1rem}
.section{margin-top:1rem}
.home-hero{display:grid;gap:1.5rem;grid-template-columns:minmax(0,1fr) minmax(0,320px);align-items:flex-start}
.home-hero-main{display:flex;flex-direction:column;gap:1rem}
.home-title{margin:.2rem 0;color:var(--maroon);line-height:1.15}
.home-sub{margin:0;color:var(--muted)}
.link{color:var(--maroon);text-decoration:none}
.link:hover{text-decoration:underline}
.home-actions{display:flex;gap:.6rem;flex-wrap:wrap}
.home-benefits{list-style:none;margin:0;padding:0;display:grid;gap:.6rem}
.home-benefits li{display:flex;gap:.5rem;align-items:flex-start;color:var(--muted)}
.home-benefits li span:first-child{font-size:1.2rem;line-height:1.5}
.home-benefits strong{color:var(--maroon)}
.home-hero-aside{padding:1rem;border:1px dashed var(--border);border-radius:.75rem;display:flex;flex-direction:column;gap:.75rem;background:rgba(123,45,38,.03)}
.home-hero-aside h2{margin:0;color:var(--maroon)}
.home-hero-aside ul{list-style:none;margin:0;padding:0;display:grid;gap:.5rem;color:var(--muted);font-size:.95rem}
.home-hero-aside li{display:flex;gap:.4rem;align-items:flex-start}
.home-hero-aside .emoji{font-size:1.1rem;line-height:1.4}
.home-tips{display:grid;gap:1rem;margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
.home-tip{padding:1rem;border:1px solid var(--border);border-radius:.75rem;display:flex;flex-direction:column;gap:.5rem;background:var(--card,#fff)}
.home-tip h3{margin:0;color:var(--maroon)}
.home-tip p{margin:0;color:var(--muted);font-size:.95rem}

.mesa-card{display:flex;gap:1rem;align-items:flex-start}
.mesa-thumb{flex:0 0 160px;border-radius:.6rem;overflow:hidden;border:1px solid var(--border);background:#f8f9fa}
.mesa-thumb img{width:100%;height:120px;object-fit:cover;display:block}
.mesa-body{flex:1 1 auto;min-width:0}
.mesa-title{margin:0 0 .25rem;line-height:1.2}
.mesa-desc{margin:.25rem 0 .5rem;color:var(--muted)}
.mesa-meta{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin:.25rem 0 .5rem}

.pill{padding:.15rem .6rem;border-radius:999px;border:1px solid var(--border);font-size:.85rem;line-height:1.5}
.pill.ok{background:#e7f8f1;color:#065f46;border-color:#a7e6cf}
.pill.off{background:#f3f4f6;color:#374151}

.mesa-avatars{display:flex;align-items:center;gap:.35rem;margin:.35rem 0}
.mesa-avatars .ava{width:28px;height:28px;border-radius:999px;overflow:hidden;border:1px solid var(--border);display:inline-block}
.mesa-avatars .ava img{width:100%;height:100%;object-fit:cover;display:block}
.mesa-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem}

.mesas-grid{display:grid;gap:1rem;margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
.mesa-mini{display:flex;flex-direction:column;gap:.5rem;padding:1rem;border:1px solid var(--border);border-radius:.75rem;background:#fff}
.mesa-mini-thumb{border-radius:.6rem;overflow:hidden;border:1px solid var(--border)}
.mesa-mini-thumb img{width:100%;height:160px;object-fit:cover;display:block}
.mesa-mini-title{margin:0;font-size:1.05rem;line-height:1.35}
.mesa-mini-desc{margin:0;color:var(--muted);font-size:.95rem}
.mesa-mini-footer{margin-top:auto;display:flex;gap:.5rem;flex-wrap:wrap}

.cta-explore{padding:1rem;border:1px dashed var(--border);border-radius:.6rem;text-align:center}
.muted{color:var(--muted)}

@media (max-width:640px){
  .mesa-card{flex-direction:column}
  .mesa-thumb{flex:0 0 auto;width:100%}
  .mesa-thumb img{height:180px}
}

@media (max-width:860px){
  .home-hero{grid-template-columns:1fr}
}

@media (prefers-color-scheme: dark){
  :root{--border:#2d2f33; --muted:#a7b0ba}
  .home-divider{background:#2d2f33}
  .mesa-thumb{border-color:#2d2f33;background:#151618}
  .pill.ok{background:#0a2e22;color:#b8f5e1;border-color:#1f5a46}
  .pill.off{background:#1a1c1f;color:#d1d5db;border-color:#2d2f33}
  .cta-explore{border-color:#2d2f33}
  .mesa-mini{background:#151618;border-color:#2d2f33}
  .mesa-mini-thumb{border-color:#2d2f33}
}
</style>
@endpush

@section('content')
<main class="home-wrap" aria-labelledby="home-title">
  @php $myMesaContext = $myMesaContext ?? []; @endphp
  {{-- HERO --}}
  <section class="card card-pad home-hero">
    <div class="home-hero-main">
      <h1 id="home-title" class="home-title">Bienvenid@ a {{ config('app.name', 'La Taberna') }}</h1>
      <p class="home-sub">Organizá partidas, descubrí mesas abiertas y sumate a la comunidad.</p>

      <ul class="home-benefits">
        <li><span>🎲</span> <span>{{ __('Armá mesas y administrá inscripciones sin planillas externas.') }}</span></li>
        <li><span>📝</span> <span>{{ __('Compartí notas privadas con tus jugadores y dejá todo documentado.') }}</span></li>
        <li><span>🏅</span> <span>{{ __('Seguimiento de asistencia y honor automatizado para cada jugador.') }}</span></li>
      </ul>

      @php
      $mesasIndexUrl  = \Illuminate\Support\Facades\Route::has('mesas.index')  ? route('mesas.index')  : url('/mesas');
      $mesasCreateUrl = \Illuminate\Support\Facades\Route::has('mesas.create') ? route('mesas.create') : url('/mesas/create');
      $panelUrl       = \Illuminate\Support\Facades\Route::has('dashboard')    ? route('dashboard')    : url('/panel');
      $loginUrl       = \Illuminate\Support\Facades\Route::has('login')        ? route('login')        : url('/login');
      $registerUrl    = \Illuminate\Support\Facades\Route::has('register')     ? route('register')     : url('/register');
    @endphp

      <nav class="home-actions" aria-label="Acciones principales">
        @auth
          <a class="btn" href="{{ $mesasIndexUrl }}">{{ __('Mis mesas') }}</a>

          {{-- Mostrar "Crear mesa" según policy (GameTablePolicy@create) --}}
          @can('create', \App\Models\GameTable::class)
            <a class="btn gold" href="{{ $mesasCreateUrl }}">➕ {{ __('Crear mesa') }}</a>
          @endcan

          <a class="btn" href="{{ $panelUrl }}">{{ __('Ir a mi panel') }}</a>
        @else
          <a class="btn" href="{{ $registerUrl }}">{{ __('Crear cuenta') }}</a>
          <a class="btn" href="{{ $loginUrl }}">{{ __('Entrar') }}</a>
        @endauth
      </nav>
    </div>

    <aside class="home-hero-aside">
      <h2>{{ __('Todo lo importante en un solo lugar') }}</h2>
      <ul>
        <li><span class="emoji">✅</span> <span>{{ __('Confirmá asistencia y comportamiento con un clic por jugador.') }}</span></li>
        <li><span class="emoji">🔐</span> <span>{{ __('Notas visibles solo para inscriptos y encargados.') }}</span></li>
        <li><span class="emoji">📈</span> <span>{{ __('Seguimiento de honor y estadísticas al día.') }}</span></li>
        @if(($myMesaContext['canSeeNotes'] ?? false) && !empty($myMesaContext['notesUrl']))
          <li><span class="emoji">🗒️</span> <span><a class="link" href="{{ $myMesaContext['notesUrl'] }}">{{ __('Acceder a las notas de mi mesa') }}</a></span></li>
        @endif
      </ul>
    </aside>
  </section>

  {{-- Tu mesa actual (si existe) --}}
  @php $myMesa = $myMesa ?? null; @endphp
  @auth
    @isset($myMesa)
      @php
        $mesaShowUrl = \Illuminate\Support\Facades\Route::has('mesas.show')
          ? route('mesas.show', $myMesa)
          : url('/mesas/'.$myMesa->id);
      @endphp

      <section class="card card-pad section" aria-labelledby="my-table-title">
        <header style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.5rem">
          <h2 id="my-table-title" style="margin:.2rem 0">Tu mesa actual</h2>
          <small class="muted">
            {{ optional($myMesa->updated_at)->diffForHumans(['parts'=>1,'short'=>true]) ?? 'recién' }}
          </small>
        </header>

        @if(($hasMesaCardPartial ?? false))
          @includeFirst(['mesas._card','tables._card'], [
            'mesa' => $myMesa,
            'myMesaId' => $myMesa->id,
            'alreadyThis' => true,
          ])
        @else
          {{-- Fallback liviano sin dependencias --}}
          <article class="mesa-card" aria-labelledby="mesa-title-{{ $myMesa->id }}">
            {{-- Imagen (usa accessor image_url_resolved del modelo) --}}
            @if(!empty($myMesa->image_url_resolved))
              <a class="mesa-thumb" href="{{ $mesaShowUrl }}">
                <img
                  src="{{ $myMesa->image_url_resolved }}"
                  alt="Imagen de {{ $myMesa->title }}"
                  loading="lazy" decoding="async" width="320" height="180">
              </a>
            @endif

            <div class="mesa-body">
              <h3 id="mesa-title-{{ $myMesa->id }}" class="mesa-title">
                <a href="{{ $mesaShowUrl }}" class="link">{{ $myMesa->title }}</a>
              </h3>

              @if(!empty($myMesa->description))
                <p class="mesa-desc">{{ \Illuminate\Support\Str::limit($myMesa->description, 180) }}</p>
              @endif

              <div class="mesa-meta">
                <span class="pill">
                  {{ (int)($myMesa->signups_count ?? 0) }}/{{ (int)($myMesa->capacity ?? 0) }} jugadores
                </span>

                @if($myMesa->is_open_now ?? false)
                  <span class="pill ok">Abierta</span>
                @else
                  <span class="pill off">Cerrada</span>
                @endif

                @if(!empty($myMesa->opens_at))
                  @php
                    $oa = $myMesa->opens_at instanceof \Carbon\CarbonInterface
                      ? \Carbon\Carbon::instance($myMesa->opens_at)
                      : \Carbon\Carbon::parse($myMesa->opens_at);
                  @endphp
                  <span class="pill" title="{{ $oa->toDayDateTimeString() }}">
                    Abre {{ $oa->diffForHumans() }}
                  </span>
                @endif
              </div>

              {{-- Últimos inscriptos (si vinieron eager-loaded) --}}
              @if($myMesa->relationLoaded('recentSignups') && $myMesa->recentSignups->isNotEmpty())
                <div class="mesa-avatars" aria-label="Inscripciones recientes">
                  @foreach($myMesa->recentSignups as $s)
                    @php
                      $u = $s->user;
                      $avatar = $u?->avatar_url ?? null;
                      $name = $u?->name ?? $u?->username ?? (\Illuminate\Support\Str::before($u?->email ?? 'U', '@'));
                    @endphp
                    <span class="ava" title="{{ $name }}">
                      <img
                        src="{{ $avatar ?: asset('images/avatar-placeholder.png') }}"
                        alt="{{ $name }}" loading="lazy" decoding="async" width="28" height="28">
                    </span>
                  @endforeach
                  <span class="muted" style="font-size:.85rem">+ recientes</span>
                </div>
              @endif

              <div class="mesa-actions">
                <a class="btn" href="{{ $mesaShowUrl }}">Ver mesa</a>
                @if(($myMesaContext['canSeeNotes'] ?? false) && !empty($myMesaContext['notesUrl']))
                  <a class="btn" href="{{ $myMesaContext['notesUrl'] }}">Notas de la mesa</a>
                @endif
                <a class="btn" href="{{ $mesaShowUrl }}#jugadores">Ver jugadores</a>
                <a class="btn" href="{{ $mesasIndexUrl }}">Explorar otras</a>
              </div>
            </div>
          </article>
        @endif
      </section>
    @endisset
  @endauth

  {{-- Descubrimiento / últimas mesas --}}
  <section class="card card-pad section">
    @php
      $partials = ['mesas._home_latest','tables._home_latest','mesas._cards','tables._cards'];
      $partial = collect($partials)->first(fn($v) => \Illuminate\Support\Facades\View::exists($v));
    @endphp

    @php $latestTables = $latestTables ?? collect(); @endphp

    @if($partial)
      @include($partial)
    @else
      @if($latestTables->isNotEmpty())
        <div class="mesas-grid" aria-live="polite">
          @foreach($latestTables as $mesa)
            <article class="mesa-mini" aria-labelledby="mesa-mini-{{ $mesa['id'] }}">
              @if(!empty($mesa['image']))
                <a class="mesa-mini-thumb" href="{{ $mesa['url'] }}">
                  <img
                    src="{{ $mesa['image'] }}"
                    alt="Imagen de {{ $mesa['title'] }}"
                    loading="lazy" decoding="async" width="320" height="180">
                </a>
              @endif

              <h3 id="mesa-mini-{{ $mesa['id'] }}" class="mesa-mini-title">
                <a href="{{ $mesa['url'] }}" class="link">{{ $mesa['title'] }}</a>
              </h3>

              @if(!empty($mesa['excerpt']))
                <p class="mesa-mini-desc">{{ $mesa['excerpt'] }}</p>
              @endif

              <div class="mesa-meta">
                <span class="pill">{{ $mesa['players_label'] }}</span>
                <span class="pill {{ $mesa['status_class'] }}">{{ $mesa['status_label'] }}</span>

                @if(!empty($mesa['opens_at_human']))
                  <span class="pill" title="{{ $mesa['opens_at_title'] }}">Abre {{ $mesa['opens_at_human'] }}</span>
                @endif
              </div>

              @if(!empty($mesa['updated_human']))
                <p class="muted" style="margin:0;font-size:.8rem">Actualizada {{ $mesa['updated_human'] }}</p>
              @endif

              <div class="mesa-mini-footer">
                <a class="btn" href="{{ $mesa['url'] }}">Ver mesa</a>
                <a class="btn" href="{{ $mesasIndexUrl }}">Ver todas</a>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="cta-explore">
          <p class="muted" style="margin:0 0 .6rem">¿Buscás una partida?</p>
          <a class="btn ok" href="{{ $mesasIndexUrl }}">Explorar mesas abiertas</a>
        </div>
      @endif
    @endif
  </section>

  <section class="card card-pad section">
    <h2 style="margin-top:0;color:var(--maroon)">{{ __('Herramientas para encargados') }}</h2>
    <div class="home-tips">
      <article class="home-tip">
        <h3>🗒️ {{ __('Notas compartidas') }}</h3>
        <p>{{ __('Guardá recordatorios, consignas o enlaces especiales y compartilos solo con tu mesa.') }}</p>
      </article>
      <article class="home-tip">
        <h3>👥 {{ __('Control de asistencia') }}</h3>
        <p>{{ __('Confirmá asistencia o marcá ausencias sin salir de la plataforma y sumá honor automáticamente.') }}</p>
      </article>
      <article class="home-tip">
        <h3>🧙 {{ __('Encargado siempre presente') }}</h3>
        <p>{{ __('Figurás como jugador por defecto y podés liberar tu lugar con un solo clic si preferís dirigir.') }}</p>
      </article>
    </div>
  </section>

  {{-- DEBUG (solo con APP_DEBUG=true) --}}
  @if(config('app.debug'))
    <aside class="muted section" style="font-size:.9rem">
      <strong>DEBUG:</strong>
      {{ auth()->check() ? 'user_id='.auth()->id() : 'guest' }},
      myMesa = {{ $myMesa?->id ? "ID {$myMesa->id}" : 'null' }}
    </aside>
  @endif
</main>
@endsection
