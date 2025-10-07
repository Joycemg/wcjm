{{-- resources/views/home.blade.php --}}
@extends('layouts.app')

@section('title', config('app.name', 'La Taberna') . ' · Rol & Juegos')

@push('head')
  <meta name="description" content="Organizá partidas, descubrí mesas abiertas y sumate a la comunidad.">
  <style>
    /* ===== Home: sólo estilos específicos del HERO / rejillas ===== */

    .home-wrap{max-width:1100px;margin-inline:auto;padding:clamp(1rem,3vw,1.6rem);display:grid;gap:1.2rem}

    /* HERO con alto contraste y misma paleta */
    .home-hero{
      position:relative;overflow:hidden;display:grid;gap:1.4rem;
      grid-template-columns:minmax(0,1fr) minmax(0,330px);align-items:stretch;
      background:
        radial-gradient(900px 500px at 10% -10%, rgba(200,162,76,.10), transparent 60%),
        radial-gradient(700px 420px at 110% 110%, rgba(47,133,90,.12), transparent 60%),
        linear-gradient(180deg, rgba(38,33,30,.96), rgba(38,33,30,.94));
      border:1px solid rgba(217,207,195,.25);
      box-shadow:var(--shadow-lg);
      border-radius:.5rem;
    }
    .home-hero::before,.home-hero::after{content:"";position:absolute;pointer-events:none}
    .home-hero::before{inset:-38% -25% auto -25%;height:420px;background:radial-gradient(circle,#f4d98a66 0,#f4d98a00 60%)}
    .home-hero::after{inset:auto -15% -40% -25%;height:360px;background:radial-gradient(circle,#63b58844 0,#63b58800 60%)}
    .home-hero-main,.home-hero-aside{position:relative;z-index:1}

    .home-title{margin:.1rem 0;color:#fff7ee;letter-spacing:.01em;line-height:1.1;
      font-weight:800;font-size:clamp(2rem,3.8vw,2.6rem);text-shadow:0 10px 28px rgba(0,0,0,.45)}
    .home-sub{margin:0;color:#f8efe0;font-size:1.05rem}

    .home-benefits{list-style:none;margin:.4rem 0 0;padding:0;display:grid;gap:.55rem}
    .home-benefits li{display:flex;gap:.6rem;align-items:center;color:#fff7ee;background:rgba(255,255,255,.08);
      border:1px solid rgba(255,255,255,.14);border-radius:.5rem;padding:.45rem .75rem}
    .home-benefits li span:first-child{font-size:1.2rem;line-height:1}

    .home-actions{display:flex;gap:.6rem;flex-wrap:wrap}

    .home-hero-aside{
      padding:1.1rem;border:1px solid rgba(217,207,195,.45);border-radius:.5rem;
      background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88));
      box-shadow:0 12px 30px rgba(0,0,0,.25)
    }
    .home-hero-aside h2{margin:0 0 .35rem;color:#1f2937;font-weight:700;font-size:1.08rem}
    .home-hero-aside ul{list-style:none;margin:0;padding:0;display:grid;gap:.5rem;color:#1f2937}
    .home-hero-aside li{display:grid;grid-template-columns:auto 1fr;gap:.5rem;align-items:start}
    .home-hero-aside .emoji{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:.45rem;background:rgba(200,162,76,.25)}

    /* Últimas mesas (fallback) */
    .mesas-grid{display:grid;gap:1rem;margin-top:.4rem;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
    .mesa-mini{display:flex;flex-direction:column;gap:.6rem;padding:1rem;border-radius:.5rem;background:var(--surface);
      border:1px solid rgba(217,207,195,.85);box-shadow:var(--shadow-sm)}
    .mesa-mini-thumb{border-radius:.5rem;overflow:hidden;border:2px solid var(--line)}
    .mesa-mini-thumb img{width:100%;height:160px;object-fit:cover;display:block}
    .mesa-mini-title{margin:0;font-size:1.06rem;line-height:1.35;color:#111827;font-weight:800}
    .mesa-mini-desc{margin:0;color:var(--muted);font-size:.95rem}

    .cta-explore{padding:1rem;border:2px dashed rgba(200,162,76,.6);border-radius:.5rem;text-align:center;background:#faf7f1;color:#471010;font-weight:600}

    @media (max-width:860px){.home-hero{grid-template-columns:1fr}.home-hero-aside{order:-1}}
    @media (max-width:640px){
      .home-wrap{padding:.9rem}
      .home-hero-aside{padding:.9rem}
    }
  </style>
@endpush

@section('content')
  @php
    /** @var \App\Models\GameTable|null $myMesa */
    use Illuminate\Support\Facades\Route as LRoute;
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    $myMesaContext = $myMesaContext ?? [];
    $latestTables = $latestTables ?? collect();

    $mesasIndexUrl  = LRoute::has('mesas.index')  ? route('mesas.index')  : url('/mesas');
    $mesasCreateUrl = LRoute::has('mesas.create') ? route('mesas.create') : url('/mesas/create');
    $panelUrl       = LRoute::has('dashboard')    ? route('dashboard')    : url('/panel');
    $loginUrl       = LRoute::has('login')        ? route('login')        : url('/login');
    $registerUrl    = LRoute::has('register')     ? route('register')     : url('/register');
  @endphp

  <main class="home-wrap" aria-labelledby="home-title">
    {{-- HERO --}}
    <section class="card home-hero" role="region" aria-labelledby="home-title">
      <div class="home-hero-main">
        <h1 id="home-title" class="home-title">
          {{ __('Bienvenid@ a :name', ['name' => config('app.name', 'La Taberna')]) }}
        </h1>
        <p class="home-sub">
          {{ __('Organizá partidas, descubrí mesas abiertas y sumate a la comunidad.') }}
        </p>

        <ul class="home-benefits" aria-label="{{ __('Beneficios principales') }}">
          <li><span aria-hidden="true">🎲</span><span>{{ __('Armá mesas y administrá inscripciones sin planillas externas.') }}</span></li>
          <li><span aria-hidden="true">📝</span><span>{{ __('Compartí notas privadas con tus jugadores y dejá todo documentado.') }}</span></li>
          <li><span aria-hidden="true">🏅</span><span>{{ __('Seguimiento de asistencia y honor automatizado por jugador.') }}</span></li>
        </ul>

        <nav class="home-actions" aria-label="Acciones principales">
          @auth
            @can('create', \App\Models\GameTable::class)
              <a class="btn gold" href="{{ $mesasCreateUrl }}">➕ {{ __('Crear mesa') }}</a>
            @endcan
            <a class="btn" href="{{ $mesasIndexUrl }}">{{ __('Mis mesas') }}</a>
            <a class="btn" href="{{ $panelUrl }}">{{ __('Ir a mi panel') }}</a>
          @else
            <a class="btn gold" href="{{ $registerUrl }}">{{ __('Crear cuenta') }}</a>
            <a class="btn" href="{{ $loginUrl }}">{{ __('Entrar') }}</a>
          @endauth
        </nav>
      </div>

      <aside class="home-hero-aside card" aria-labelledby="aside-title">
        <h2 id="aside-title">{{ __('Todo lo importante en un solo lugar') }}</h2>
        <ul>
          <li><span class="emoji" aria-hidden="true">✅</span><span>{{ __('Confirmá asistencia y comportamiento con un clic por jugador.') }}</span></li>
          <li><span class="emoji" aria-hidden="true">🔐</span><span>{{ __('Notas visibles solo para inscriptos y encargados.') }}</span></li>
          <li><span class="emoji" aria-hidden="true">📈</span><span>{{ __('Seguimiento de honor y estadísticas al día.') }}</span></li>
          @if(($myMesaContext['canSeeNotes'] ?? false) && !empty($myMesaContext['notesUrl']))
            <li><span class="emoji" aria-hidden="true">🗒️</span><span><a class="link" href="{{ $myMesaContext['notesUrl'] }}">{{ __('Acceder a las notas de mi mesa') }}</a></span></li>
          @endif
        </ul>
      </aside>
    </section>

    {{-- Tu mesa actual --}}
    @auth
      @isset($myMesa)
        @php
          $mesaShowUrl = LRoute::has('mesas.show') ? route('mesas.show', $myMesa) : url('/mesas/' . ($myMesa->id ?? ''));
          $opensAtHuman = null;
          if (!empty($myMesa->opens_at)) {
            $oa = $myMesa->opens_at instanceof \Carbon\CarbonInterface
              ? Carbon::instance($myMesa->opens_at)
              : Carbon::parse($myMesa->opens_at);
            $opensAtHuman = $oa?->diffForHumans();
          }
        @endphp

        <section class="card" role="region" aria-labelledby="my-table-title" style="padding:clamp(.9rem,2.4vw,1.2rem)">
          <header style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.5rem">
            <h2 id="my-table-title" style="margin:.2rem 0;color:#111827">{{ __('Tu mesa actual') }}</h2>
            <small class="muted">
              {{ optional($myMesa->updated_at)->diffForHumans(['parts' => 1, 'short' => true]) ?? __('recién') }}
            </small>
          </header>

          @if(($hasMesaCardPartial ?? false))
            @includeFirst(['mesas._card', 'tables._card'], [
              'mesa' => $myMesa,
              'myMesaId' => $myMesa->id ?? null,
              'alreadyThis' => true,
            ])
          @else
            {{-- Fallback sin dependencias, usando clases del tema --}}
            <article class="mesa-card" aria-labelledby="mesa-title-{{ $myMesa->id ?? 'x' }}">
              @if(!empty($myMesa->image_url_resolved))
                <a class="mesa-thumb" href="{{ $mesaShowUrl }}" style="flex:0 0 260px;border-radius:.5rem;overflow:hidden;border:2px solid var(--line)">
                  <img src="{{ $myMesa->image_url_resolved }}"
                       alt="{{ __('Imagen de :title', ['title' => e($myMesa->title ?? '')]) }}"
                       width="640" height="360" loading="lazy" decoding="async"
                       style="width:100%;height:160px;object-fit:cover;display:block">
                </a>
              @endif>

              <div class="mesa-body">
                <h3 id="mesa-title-{{ $myMesa->id ?? 'x' }}" class="mesa-title" style="color:#111827">
                  <a href="{{ $mesaShowUrl }}" class="link">{{ e($myMesa->title ?? __('Mesa')) }}</a>
                </h3>

                @if(!empty($myMesa->description))
                  <p class="mesa-desc">{{ Str::limit((string) $myMesa->description, 180) }}</p>
                @endif

                <div class="mesa-meta">
                  <span class="pill">{{ (int) ($myMesa->signups_count ?? 0) }}/{{ (int) ($myMesa->capacity ?? 0) }} {{ __('jugadores') }}</span>
                  @if($myMesa->is_open_now ?? false)
                    <span class="pill ok">{{ __('Abierta') }}</span>
                  @else
                    <span class="pill off">{{ __('Cerrada') }}</span>
                  @endif
                  @if($opensAtHuman)
                    <span class="pill" title="{{ optional($oa ?? null)?->toDayDateTimeString() }}">{{ __('Abre') }} {{ $opensAtHuman }}</span>
                  @endif
                </div>

                <div class="mesa-actions" style="display:flex;gap:.55rem;flex-wrap:wrap;margin-top:.6rem">
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

    {{-- Últimas mesas / descubrimiento --}}
    <section class="card" role="region" aria-labelledby="latest-title" style="padding:clamp(.9rem,2.4vw,1.2rem)">
      <h2 id="latest-title" class="sr-only">{{ __('Últimas mesas') }}</h2>
      @php
        $partials = ['mesas._home_latest', 'tables._home_latest', 'mesas._cards', 'tables._cards'];
        $partial = collect($partials)->first(fn($v) => \Illuminate\Support\Facades\View::exists($v));
      @endphp

      @if($partial)
        @include($partial)
      @else
        @if($latestTables->isNotEmpty())
          <div class="mesas-grid" aria-live="polite">
            @foreach($latestTables as $mesa)
              @php
                $mId  = $mesa['id'] ?? Str::uuid()->toString();
                $mUrl = $mesa['url'] ?? $mesasIndexUrl;
                $mTit = $mesa['title'] ?? __('Mesa');
                $mImg = $mesa['image'] ?? null;
              @endphp
              <article class="mesa-mini" aria-labelledby="mesa-mini-{{ $mId }}">
                @if($mImg)
                  <a class="mesa-mini-thumb" href="{{ $mUrl }}">
                    <img src="{{ $mImg }}" alt="{{ __('Imagen de :title', ['title' => e($mTit)]) }}"
                         width="320" height="180" loading="lazy" decoding="async">
                  </a>
                @endif

                <h3 id="mesa-mini-{{ $mId }}" class="mesa-mini-title">
                  <a href="{{ $mUrl }}" class="link">{{ e($mTit) }}</a>
                </h3>

                @if(!empty($mesa['excerpt']))
                  <p class="mesa-mini-desc">{{ $mesa['excerpt'] }}</p>
                @endif

                <div class="mesa-meta">
                  @if(!empty($mesa['players_label'])) <span class="pill">{{ $mesa['players_label'] }}</span> @endif
                  @if(!empty($mesa['status_label']))  <span class="pill {{ $mesa['status_class'] ?? '' }}">{{ $mesa['status_label'] }}</span> @endif
                  @if(!empty($mesa['opens_at_human'])) <span class="pill" title="{{ $mesa['opens_at_title'] ?? '' }}">{{ __('Abre') }} {{ $mesa['opens_at_human'] }}</span> @endif
                </div>

                @if(!empty($mesa['updated_human']))
                  <p class="muted" style="margin:0;font-size:.8rem">{{ __('Actualizada') }} {{ $mesa['updated_human'] }}</p>
                @endif

                <div style="display:flex;gap:.55rem;flex-wrap:wrap;margin-top:.5rem">
                  <a class="btn" href="{{ $mUrl }}">{{ __('Ver mesa') }}</a>
                  <a class="btn" href="{{ $mesasIndexUrl }}">{{ __('Ver todas') }}</a>
                </div>
              </article>
            @endforeach
          </div>
        @else
          <div class="cta-explore" role="note">
            <p class="muted" style="margin:0 0 .5rem">{{ __('¿Buscás una partida?') }}</p>
            <a class="btn ok" href="{{ $mesasIndexUrl }}">{{ __('Explorar mesas abiertas') }}</a>
          </div>
        @endif
      @endif
    </section>

    {{-- Tips --}}
    <section class="card" role="region" aria-labelledby="tools-title" style="padding:clamp(.9rem,2.4vw,1.2rem)">
      <h2 id="tools-title" style="margin-top:0;color:#111827">{{ __('Herramientas para encargados') }}</h2>
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
        <article class="card" style="padding:1rem">
          <h3>🗒️ {{ __('Notas compartidas') }}</h3>
          <p class="muted">{{ __('Guardá recordatorios, consignas o enlaces especiales y compartilos solo con tu mesa.') }}</p>
        </article>
        <article class="card" style="padding:1rem">
          <h3>👥 {{ __('Control de asistencia') }}</h3>
          <p class="muted">{{ __('Confirmá asistencia o marcá ausencias sin salir de la plataforma y sumá honor automáticamente.') }}</p>
        </article>
        <article class="card" style="padding:1rem">
          <h3>🧙 {{ __('Encargado siempre presente') }}</h3>
          <p class="muted">{{ __('Figurás como jugador por defecto y podés liberar tu lugar con un solo clic si preferís dirigir.') }}</p>
        </article>
      </div>
    </section>

    @if(config('app.debug'))
      <aside class="muted" style="font-size:.9rem">
        <strong>DEBUG:</strong>
        {{ auth()->check() ? 'user_id=' . auth()->id() : 'guest' }},
        myMesa = {{ ($myMesa?->id ?? null) ? "ID " . ($myMesa->id) : 'null' }}
      </aside>
    @endif
  </main>
@endsection
