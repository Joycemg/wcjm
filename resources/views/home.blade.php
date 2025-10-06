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
.home-hero{display:flex;flex-direction:column;gap:.75rem}
.home-title{margin:.2rem 0;color:var(--maroon);line-height:1.15}
.home-sub{margin:0;color:var(--muted)}
.home-actions{display:flex;gap:.6rem;flex-wrap:wrap}
.home-divider{height:1px;background:var(--border);margin:.5rem 0 1rem}

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

.cta-explore{padding:1rem;border:1px dashed var(--border);border-radius:.6rem;text-align:center}
.muted{color:var(--muted)}

@media (max-width:640px){
  .mesa-card{flex-direction:column}
  .mesa-thumb{flex:0 0 auto;width:100%}
  .mesa-thumb img{height:180px}
}

@media (prefers-color-scheme: dark){
  :root{--border:#2d2f33; --muted:#a7b0ba}
  .home-divider{background:#2d2f33}
  .mesa-thumb{border-color:#2d2f33;background:#151618}
  .pill.ok{background:#0a2e22;color:#b8f5e1;border-color:#1f5a46}
  .pill.off{background:#1a1c1f;color:#d1d5db;border-color:#2d2f33}
  .cta-explore{border-color:#2d2f33}
}
</style>
@endpush

@section('content')
<main class="home-wrap" aria-labelledby="home-title">
  {{-- HERO --}}
  <section class="card home-hero card-pad">
    <h1 id="home-title" class="home-title">Bienvenid@ a {{ config('app.name', 'La Taberna') }}</h1>
    <p class="home-sub">Organizá partidas, descubrí mesas abiertas y sumate a la comunidad.</p>

    <div class="home-divider" role="separator" aria-hidden="true"></div>

    @php
      $mesasIndexUrl  = \Illuminate\Support\Facades\Route::has('mesas.index')  ? route('mesas.index')  : url('/mesas');
      $mesasCreateUrl = \Illuminate\Support\Facades\Route::has('mesas.create') ? route('mesas.create') : url('/mesas/create');
      $panelUrl       = \Illuminate\Support\Facades\Route::has('dashboard')    ? route('dashboard')    : url('/panel');
      $loginUrl       = \Illuminate\Support\Facades\Route::has('login')        ? route('login')        : url('/login');
      $registerUrl    = \Illuminate\Support\Facades\Route::has('register')     ? route('register')     : url('/register');
    @endphp

    <nav class="home-actions" aria-label="Acciones principales">
      @auth
        {{-- Ir a mi mesa (si no hay, el controlador redirige) --}}
        @if(\Illuminate\Support\Facades\Route::has('mesas.mine'))
          <a class="btn" href="{{ route('mesas.mine') }}" aria-label="Ir a mi mesa">Ir a mi mesa</a>
        @else
          <a class="btn" href="{{ $mesasIndexUrl }}">Mis mesas</a>
        @endif>

        {{-- Mostrar "Crear mesa" según policy (GameTablePolicy@create) --}}
        @can('create', \App\Models\GameTable::class)
          <a class="btn gold" href="{{ $mesasCreateUrl }}">➕ Crear mesa</a>
        @endcan

        <a class="btn" href="{{ $panelUrl }}">Ir a mi panel</a>
      @else
        <a class="btn" href="{{ $registerUrl }}">Crear cuenta</a>
        <a class="btn" href="{{ $loginUrl }}">Entrar</a>
      @endauth
    </nav>
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

    @if($partial)
      @include($partial)
    @else
      <div class="cta-explore">
        <p class="muted" style="margin:0 0 .6rem">¿Buscás una partida?</p>
        <a class="btn ok" href="{{ $mesasIndexUrl }}">Explorar mesas abiertas</a>
      </div>
    @endif
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
