{{-- resources/views/home.blade.php --}}
@extends('layouts.app')

@section('title', config('app.name', 'La Taberna').' · Rol & Juegos')

@push('head')
<meta name="description" content="Organizá partidas, descubrí mesas abiertas y sumate a la comunidad.">
<style>
/* ====== Home · Comunidad de juegos de mesa ====== */
.home-wrap {
  max-width: 1040px;
  margin-inline: auto;
  padding: 1rem;
  display: grid;
  gap: 1.5rem;
}

.card-pad { padding: 1.5rem; }
.section { margin-top: 1.25rem; }

.home-hero {
  position: relative;
  overflow: hidden;
  display: grid;
  gap: 1.75rem;
  grid-template-columns: minmax(0, 1fr) minmax(0, 320px);
  align-items: stretch;
  background: linear-gradient(140deg, rgba(251, 191, 36, 0.12), rgba(52, 211, 153, 0.08));
}

.home-hero::before {
  content: "";
  position: absolute;
  inset: -40% -25% auto -25%;
  height: 420px;
  background: radial-gradient(circle at center, rgba(251, 191, 36, 0.35), rgba(251, 191, 36, 0));
  filter: blur(0.4px);
  opacity: 0.75;
  pointer-events: none;
}

.home-hero::after {
  content: "";
  position: absolute;
  inset: auto -15% -40% -25%;
  height: 360px;
  background: radial-gradient(circle at center, rgba(52, 211, 153, 0.3), rgba(52, 211, 153, 0));
  opacity: 0.6;
  pointer-events: none;
}

.home-hero-main,
.home-hero-aside {
  position: relative;
  z-index: 2;
}

.home-hero-main {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.home-title {
  margin: .2rem 0;
  font-family: 'Righteous', cursive;
  font-size: clamp(2.1rem, 4vw, 2.75rem);
  color: #fef3c7;
  text-shadow: 0 4px 12px rgba(15, 23, 42, 0.4);
  line-height: 1.1;
}

.home-sub {
  margin: 0;
  color: rgba(255, 255, 255, 0.85);
  font-size: 1.05rem;
}

.link {
  color: #fcd34d;
  text-decoration: underline;
  text-decoration-color: rgba(252, 211, 77, 0.55);
  text-decoration-thickness: 2px;
}

.link:hover { color: #fbbf24; }

.home-actions {
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
}

.home-benefits {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: .6rem;
}

.home-benefits li {
  display: flex;
  gap: .75rem;
  align-items: center;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 999px;
  padding: .4rem .9rem;
  color: rgba(255, 255, 255, 0.9);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.home-benefits li span:first-child {
  font-size: 1.25rem;
  line-height: 1.2;
}

.home-benefits strong { color: #fff; }

.home-hero-aside {
  padding: 1.35rem;
  border: 1px solid rgba(15, 23, 42, 0.12);
  border-radius: 1rem;
  display: flex;
  flex-direction: column;
  gap: .75rem;
  background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.85));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.home-hero-aside h2 {
  margin: 0;
  color: #1f2937;
  font-size: 1.1rem;
  font-weight: 700;
}

.home-hero-aside ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: .55rem;
  color: #1f2937;
  font-size: .98rem;
}

.home-hero-aside li {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: .5rem;
  align-items: start;
}

.home-hero-aside .emoji {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: rgba(251, 191, 36, 0.2);
}

.home-tips {
  display: grid;
  gap: 1.1rem;
  margin-top: 1.4rem;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.home-tip {
  padding: 1.25rem;
  border-radius: 1rem;
  display: flex;
  flex-direction: column;
  gap: .6rem;
  background: linear-gradient(160deg, rgba(15, 23, 42, 0.9), rgba(30, 64, 175, 0.6));
  color: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: 0 12px 22px rgba(15, 23, 42, 0.35);
}

.home-tip h3 {
  margin: 0;
  color: #fbbf24;
  font-size: 1.05rem;
}

.home-tip p {
  margin: 0;
  color: rgba(255, 255, 255, 0.85);
  font-size: .96rem;
}

.mesa-card {
  display: flex;
  gap: 1.1rem;
  align-items: flex-start;
}

.mesa-thumb {
  flex: 0 0 160px;
  border-radius: .8rem;
  overflow: hidden;
  border: 2px solid rgba(15, 23, 42, 0.12);
  background: rgba(15, 23, 42, 0.35);
}

.mesa-thumb img {
  width: 100%;
  height: 120px;
  object-fit: cover;
  display: block;
}

.mesa-body {
  flex: 1 1 auto;
  min-width: 0;
}

.mesa-title {
  margin: 0 0 .35rem;
  line-height: 1.2;
  color: #111827;
  font-weight: 700;
}

.mesa-desc {
  margin: .2rem 0 .5rem;
  color: #4c566a;
  font-size: .97rem;
}

.mesa-meta {
  display: flex;
  gap: .45rem;
  flex-wrap: wrap;
  align-items: center;
  margin: .25rem 0 .6rem;
}

.pill {
  padding: .15rem .65rem;
  border-radius: 999px;
  border: 1px solid rgba(17, 24, 39, 0.12);
  font-size: .85rem;
  line-height: 1.5;
  background: rgba(255, 255, 255, 0.9);
  color: #1f2937;
}

.pill.ok {
  background: rgba(52, 211, 153, 0.22);
  color: #0f5132;
  border-color: rgba(52, 211, 153, 0.4);
}

.pill.off {
  background: rgba(148, 163, 184, 0.25);
  color: #0f172a;
}

.mesa-avatars {
  display: flex;
  align-items: center;
  gap: .4rem;
  margin: .35rem 0;
}

.mesa-avatars .ava {
  width: 30px;
  height: 30px;
  border-radius: 999px;
  overflow: hidden;
  border: 2px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15);
  background: #fff;
}

.mesa-avatars .ava img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.mesa-actions {
  display: flex;
  gap: .55rem;
  flex-wrap: wrap;
  margin-top: .6rem;
}

.mesas-grid {
  display: grid;
  gap: 1.1rem;
  margin-top: 1.25rem;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}

.mesa-mini {
  display: flex;
  flex-direction: column;
  gap: .65rem;
  padding: 1.1rem;
  border-radius: 1rem;
  background: linear-gradient(160deg, rgba(255,255,255,0.95), rgba(226,232,240,0.85));
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 12px 22px rgba(15, 23, 42, 0.1);
}

.mesa-mini-thumb {
  border-radius: .75rem;
  overflow: hidden;
  border: 2px solid rgba(15, 23, 42, 0.12);
}

.mesa-mini-thumb img {
  width: 100%;
  height: 160px;
  object-fit: cover;
  display: block;
}

.mesa-mini-title {
  margin: 0;
  font-size: 1.08rem;
  line-height: 1.35;
  color: #111827;
  font-weight: 700;
}

.mesa-mini-desc {
  margin: 0;
  color: #4c566a;
  font-size: .95rem;
}

.mesa-mini-footer {
  margin-top: auto;
  display: flex;
  gap: .55rem;
  flex-wrap: wrap;
}

.cta-explore {
  padding: 1.15rem;
  border: 2px dashed rgba(251, 191, 36, 0.6);
  border-radius: .9rem;
  text-align: center;
  background: rgba(15, 23, 42, 0.75);
  color: rgba(255, 255, 255, 0.9);
  font-weight: 600;
  letter-spacing: .01em;
}

.muted {
  color: #4c566a;
  font-size: .95rem;
}

@media (max-width: 860px) {
  .home-hero {
    grid-template-columns: 1fr;
  }

  .home-hero-aside {
    order: -1;
  }
}

@media (max-width: 640px) {
  .home-wrap {
    padding: .75rem;
  }

  .card-pad {
    padding: 1rem;
  }

  .mesa-card {
    flex-direction: column;
  }

  .mesa-thumb {
    flex: 0 0 auto;
    width: 100%;
  }

  .mesa-thumb img {
    height: 180px;
  }
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

    <aside class="home-hero-aside" aria-labelledby="aside-title">
      <h2 id="aside-title">{{ __('Todo lo importante en un solo lugar') }}</h2>
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
          <h2 id="my-table-title" style="margin:.2rem 0">{{ __('Tu mesa actual') }}</h2>
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
            @if(!empty($myMesa->image_url_resolved))
              <a class="mesa-thumb" href="{{ $mesaShowUrl }}">
                <img
                  src="{{ $myMesa->image_url_resolved }}"
                  alt="Imagen de {{ $myMesa->title }}"
                  width="320" height="180"
                  loading="lazy" decoding="async">
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
                  {{ (int)($myMesa->signups_count ?? 0) }}/{{ (int)($myMesa->capacity ?? 0) }} {{ __('jugadores') }}
                </span>

                @if($myMesa->is_open_now ?? false)
                  <span class="pill ok">{{ __('Abierta') }}</span>
                @else
                  <span class="pill off">{{ __('Cerrada') }}</span>
                @endif

                @if(!empty($myMesa->opens_at))
                  @php
                    $oa = $myMesa->opens_at instanceof \Carbon\CarbonInterface
                      ? \Carbon\Carbon::instance($myMesa->opens_at)
                      : \Carbon\Carbon::parse($myMesa->opens_at);
                  @endphp
                  <span class="pill" title="{{ $oa->toDayDateTimeString() }}">
                    {{ __('Abre') }} {{ $oa->diffForHumans() }}
                  </span>
                @endif
              </div>

              @if($myMesa->relationLoaded('recentSignups') && $myMesa->recentSignups->isNotEmpty())
                <div class="mesa-avatars" aria-label="{{ __('Inscripciones recientes') }}">
                  @foreach($myMesa->recentSignups as $s)
                    @php
                      $u = $s->user;
                      $avatar = $u?->avatar_url ?? null;
                      $name = $u?->name ?? $u?->username ?? (\Illuminate\Support\Str::before($u?->email ?? 'U', '@'));
                    @endphp
                    <span class="ava" title="{{ $name }}">
                      <img
                        src="{{ $avatar ?: asset('images/avatar-placeholder.png') }}"
                        alt="{{ $name }}" width="28" height="28"
                        loading="lazy" decoding="async">
                    </span>
                  @endforeach
                  <span class="muted" style="font-size:.85rem">+ {{ __('recientes') }}</span>
                </div>
              @endif

              <div class="mesa-actions">
                <a class="btn" href="{{ $mesaShowUrl }}">{{ __('Ver mesa') }}</a>
                @if(($myMesaContext['canSeeNotes'] ?? false) && !empty($myMesaContext['notesUrl']))
                  <a class="btn" href="{{ $myMesaContext['notesUrl'] }}">{{ __('Notas de la mesa') }}</a>
                @endif
                <a class="btn" href="{{ $mesaShowUrl }}#jugadores">{{ __('Ver jugadores') }}</a>
                <a class="btn" href="{{ $mesasIndexUrl }}">{{ __('Explorar otras') }}</a>
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
      $latestTables = $latestTables ?? collect();
    @endphp

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
                    width="320" height="180"
                    loading="lazy" decoding="async">
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
                  <span class="pill" title="{{ $mesa['opens_at_title'] }}">{{ __('Abre') }} {{ $mesa['opens_at_human'] }}</span>
                @endif
              </div>

              @if(!empty($mesa['updated_human']))
                <p class="muted" style="margin:0;font-size:.8rem">{{ __('Actualizada') }} {{ $mesa['updated_human'] }}</p>
              @endif

              <div class="mesa-mini-footer">
                <a class="btn" href="{{ $mesa['url'] }}">{{ __('Ver mesa') }}</a>
                <a class="btn" href="{{ $mesasIndexUrl }}">{{ __('Ver todas') }}</a>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="cta-explore">
          <p class="muted" style="margin:0 0 .6rem">{{ __('¿Buscás una partida?') }}</p>
          <a class="btn ok" href="{{ $mesasIndexUrl }}">{{ __('Explorar mesas abiertas') }}</a>
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

  @if(config('app.debug'))
    <aside class="muted section" style="font-size:.9rem">
      <strong>DEBUG:</strong>
      {{ auth()->check() ? 'user_id='.auth()->id() : 'guest' }},
      myMesa = {{ $myMesa?->id ? "ID {$myMesa->id}" : 'null' }}
    </aside>
  @endif
</main>
@endsection
