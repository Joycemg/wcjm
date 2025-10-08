{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name', 'La Taberna') . ' · Rol & Juegos')</title>
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <meta name="color-scheme"
          content="light">

    {{-- CSRF para requests JS --}}
    <meta name="csrf-token"
          content="{{ csrf_token() }}">

    {{-- Hora del servidor en UTC (ms desde epoch) para sincronía de timers --}}
    <meta name="server-now-ms"
          content="{{ now('UTC')->valueOf() }}">

    <style>
        /* === estilos tal cual los tenías (sin cambios) === */
        :root {
            --bg: #F6EADF;
            --card: #FFF7EE;
            --ink: #2E2724;
            --muted: #6F655E;
            --maroon: #7B1E1E;
            --gold: #C8A24C;
            --line: #E2D3C2;
            --maroon-press: #671717;
            --gold-press: #AD8C3D;
            --focus: #2E6FEA22
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, Arial, sans-serif;
            -webkit-text-size-adjust: 100%
        }

        a {
            color: var(--maroon);
            text-decoration: none
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px
        }

        .sr-only {
            position: absolute !important;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0
        }

        .skip-link {
            position: absolute;
            left: -9999px;
            top: auto
        }

        .skip-link:focus {
            left: 1rem;
            top: 1rem;
            z-index: 100;
            background: #fff;
            color: #111;
            padding: .5rem .75rem;
            border-radius: .5rem;
            border: 1px solid var(--line)
        }

        .muted {
            color: var(--muted)
        }

        .form-label {
            font-weight: 600;
            font-size: .95rem;
            color: var(--muted);
        }

        .form-control {
            width: 100%;
            padding: .65rem .8rem;
            border-radius: .75rem;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: 1rem;
            transition: border-color .2s ease;
        }

        .form-control:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px var(--focus);
        }

        .form-hint {
            font-size: .85rem;
            color: var(--muted);
            margin-top: .25rem;
        }

        .form-hint-warning {
            color: #92400e;
        }

        .form-alert {
            margin: 1rem 0;
            padding: .75rem .9rem;
            border-radius: .75rem;
            border: 1px solid var(--line);
            background: #fff;
        }

        .form-alert ul {
            margin: .35rem 0 0 1.1rem;
            padding: 0;
        }

        .form-alert-title {
            display: block;
            font-weight: 600;
            margin-bottom: .35rem;
        }

        .form-alert-info {
            background: #E9F7EF;
            border-color: #BFE6CA;
            color: #166534;
        }

        .form-alert-error {
            background: #FCECEC;
            border-color: #F3B9B9;
            color: #7f1d1d;
        }

        .form-status {
            font-size: .9rem;
        }

        .form-error {
            margin: .25rem 0 0;
            color: #7f1d1d;
            font-size: .85rem;
            list-style: disc;
            padding-left: 1.1rem;
        }

        .form-error li + li {
            margin-top: .25rem;
        }

        .form-link {
            color: var(--maroon);
            text-decoration: underline;
        }

        .is-hidden {
            display: none !important;
        }

        .divider {
            height: 1px;
            background: var(--line);
            margin: clamp(.6rem, 2vw, 1rem) 0
        }

        header[role="banner"] {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 247, 238, .9);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--line)
        }

        .top {
            max-width: 1100px;
            margin: 0 auto;
            padding: .6rem clamp(.75rem, 3vw, 1rem);
            display: flex;
            align-items: center;
            gap: .6rem
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .5rem;
            color: var(--maroon)
        }

        .brand img {
            height: 34px;
            width: auto;
            display: block
        }

        .brand span {
            font-weight: 700;
            letter-spacing: .4px;
            white-space: nowrap
        }

        .grow {
            flex: 1
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 44px;
            padding: .55rem .9rem;
            border-radius: .75rem;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--ink);
            cursor: pointer;
            text-decoration: none
        }

        .btn.ok {
            background: var(--maroon);
            color: #fff;
            border-color: transparent
        }

        .btn.ok:active {
            background: var(--maroon-press)
        }

        .btn.gold {
            background: var(--gold);
            color: #2D241E;
            border-color: transparent
        }

        .btn.gold:active {
            background: var(--gold-press)
        }

        .btn.danger {
            background: #C74242;
            color: #fff;
            border-color: transparent
        }

        .btn[disabled] {
            opacity: .55;
            cursor: not-allowed
        }

        .btn.block {
            width: 100%;
            font-size: 1.05rem;
            padding: .75rem 1rem
        }

        .btn.active,
        .btn[aria-current="page"] {
            box-shadow: inset 0 0 0 2px var(--maroon)
        }

        main {
            max-width: 1100px;
            margin: 0 auto;
            padding: clamp(.75rem, 3.5vw, 1rem)
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: clamp(.75rem, 2.5vw, 1rem);
            box-shadow: 0 4px 10px rgba(0, 0, 0, .04)
        }

        .grid {
            display: grid;
            gap: clamp(.75rem, 2.5vw, 1rem)
        }

        .g2 {
            grid-template-columns: 1fr
        }

        @media (min-width:900px) {
            .g2 {
                grid-template-columns: 1.1fr .9fr
            }
        }

        label {
            display: block;
            margin: .25rem 0 .35rem;
            color: var(--muted)
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: .65rem .8rem;
            border-radius: .75rem;
            border: 1px solid var(--line);
            background: #fff;
            color: #111
        }

        input,
        textarea {
            font-size: 1rem
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--gold)
        }

        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 .4rem
        }

        th,
        td {
            padding: .6rem .7rem;
            text-align: left;
            white-space: nowrap
        }

        tbody tr {
            background: #fff;
            border-radius: .6rem
        }

        tbody tr td:first-child {
            border-top-left-radius: .6rem;
            border-bottom-left-radius: .6rem
        }

        tbody tr td:last-child {
            border-top-right-radius: .6rem;
            border-bottom-right-radius: .6rem
        }

        .cards {
            display: grid;
            gap: clamp(.75rem, 2.5vw, 1rem);
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr))
        }

        .card-game {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 1rem;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100%
        }

        .card-game .media {
            aspect-ratio: 16/10;
            background: #fff;
            overflow: hidden
        }

        .card-game .media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .card-game .body {
            padding: .9rem;
            display: flex;
            flex-direction: column;
            gap: .5rem
        }

        .badges {
            display: flex;
            gap: .4rem;
            flex-wrap: wrap
        }

        .badge {
            font-size: .8rem;
            padding: .22rem .55rem;
            border-radius: .6rem;
            border: 1px solid var(--line);
            background: #fff
        }

        .badge.open {
            background: var(--maroon);
            border-color: transparent;
            color: #fff
        }

        .badge.closed {
            background: #EEE3D6
        }

        .actions {
            display: grid;
            gap: .5rem;
            grid-template-columns: 1fr;
            margin-top: .6rem
        }

        @media (min-width:520px) {
            .actions {
                grid-template-columns: 1fr 1fr
            }
        }

        .actions>form,
        .actions>a {
            display: block
        }

        .avatars {
            display: flex;
            align-items: center;
            gap: .35rem;
            margin-top: .5rem;
            flex-wrap: wrap
        }

        .avatars img {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: #fff;
            display: block
        }

        .avatars .more {
            font-size: .8rem;
            color: var(--muted);
            padding: 0 .25rem
        }

        @media (max-width:420px) {
            .brand span {
                font-size: .95rem
            }

            .top .btn {
                padding: .5rem .7rem
            }

            .card-game .media {
                aspect-ratio: 4/3
            }

            h1,
            h2 {
                font-size: clamp(1.15rem, 5vw, 1.6rem)
            }
        }

        nav[role="navigation"]>div {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem
        }

        .flash {
            padding: .7rem .9rem;
            border-radius: .8rem;
            margin: .7rem 0;
            background: #E9F7EF;
            border: 1px solid #BFE6CA
        }

        .flash.err {
            background: #FCECEC;
            border-color: #F3B9B9
        }
    </style>

    @stack('head')
</head>

<body>
    <a href="#main"
       class="skip-link">{{ __('Saltar al contenido') }}</a>

    <header role="banner"
            aria-label="{{ __('Barra superior') }}">
        <div class="top">
            <a class="brand"
               href="{{ route('home') }}">
                <img src="{{ asset('images/logo.png') }}"
                     alt="{{ config('app.name', 'La Taberna') }}"
                     loading="lazy"
                     decoding="async">
                <span>{{ config('app.name', 'La Taberna') }} · Rol &amp; Juegos</span>
            </a>

            {{-- NAV principal --}}
            <nav aria-label="{{ __('Principal') }}"
                 style="display:flex;gap:.4rem;margin-left:.5rem">
                <a class="btn @if(request()->routeIs('home')) active @endif"
                   href="{{ route('home') }}"
                   @if(request()->routeIs('home'))
                       aria-current="page"
                   @endif>
                    {{ __('Inicio') }}
                </a>

                @if (Route::has('mesas.index'))
                    <a class="btn @if(request()->routeIs('mesas.*')) active @endif"
                       href="{{ route('mesas.index') }}"
                       @if(request()->routeIs('mesas.*'))
                           aria-current="page"
                       @endif>
                        {{ __('Mesas') }}
                    </a>
                @endif

                @if (Route::has('ranking.honor') && config('features.honor.enabled', false) && config('features.honor.ranking_public', false))
                    <a class="btn @if(request()->routeIs('ranking.*')) active @endif"
                       href="{{ route('ranking.honor') }}"
                       @if(request()->routeIs('ranking.*'))
                           aria-current="page"
                       @endif>
                        {{ __('Ranking de honor') }}
                    </a>
                @endif
            </nav>

            <div class="grow"></div>

            @auth
                @php
                    $defaultAvatar = asset(config('auth.avatars.default', 'images/avatar-default.svg'));
                    $baseAvatar = auth()->user()->avatar_url ?? $defaultAvatar;
                    $ver = optional(auth()->user()->updated_at)->timestamp;
                    $avatar = $baseAvatar . ($ver ? ('?v=' . $ver) : '');

                    // logout (si no existe la ruta, cae a /logout para evitar 500)
                    $logoutAction = \Illuminate\Support\Facades\Route::has('logout')
                        ? route('logout')
                        : url('/logout');
                  @endphp

                {{-- Perfil público --}}
                <a class="btn"
                   href="{{ route('profile.show', auth()->user()->profile_param ?? auth()->user()) }}"
                   style="gap:.6rem">
                    <img src="{{ $avatar }}"
                         alt="{{ __('Mi avatar') }}"
                         loading="lazy"
                         style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:1px solid var(--line)">
                    {{ auth()->user()->name ?? __('Mi perfil') }}
                </a>

                @if((auth()->user()->role ?? null) === 'admin' && Route::has('mesas.create'))
                    <a class="btn gold"
                       href="{{ route('mesas.create') }}">➕ {{ __('Nueva mesa') }}</a>
                @endif

                <form method="POST"
                      action="{{ $logoutAction }}"
                      style="display:inline">
                    @csrf
                    <button class="btn"
                            type="submit">{{ __('Salir') }}</button>
                </form>
            @else
                @php
                    // Fallbacks para evitar RouteNotFoundException cuando no hay scaffolding
                    $loginHref = \Illuminate\Support\Facades\Route::has('login') ? route('login') : url('/login');
                    $registerHref = \Illuminate\Support\Facades\Route::has('register') ? route('register') : url('/register');
                  @endphp

                {{-- Mantiene href como fallback, pero abre el modal por JS si existe --}}
                <a class="btn"
                   href="{{ $loginHref }}"
                   data-login-open>{{ __('Entrar') }}</a>

                @if (\Illuminate\Support\Facades\Route::has('register'))
                    <a class="btn"
                       href="{{ $registerHref }}"
                       data-register-open>{{ __('Registrarse') }}</a>
                @endif
            @endauth
        </div>
    </header>

    <main id="main"
          role="main"
          tabindex="-1">
        @if(session('ok'))
            <div class="flash"
                 role="status"
                 aria-live="polite">{{ session('ok') }}</div>
        @endif

        @if(session('err'))
            <div class="flash err"
                 role="alert">{{ session('err') }}</div>
        @endif

        @if($errors->any())
            <div class="flash err"
                 role="alert">
                <strong>{{ __('Revisá los campos:') }}</strong>
                <ul style="margin:.4rem 0 0 1rem">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- ===== MODALES GLOBALES (sólo si existen) ===== --}}
    @php
        $hasLoginModal = \Illuminate\Support\Facades\View::exists('components.auth.login-modal')
            || class_exists(\App\View\Components\Auth\LoginModal::class);
        $hasRegisterModal = \Illuminate\Support\Facades\View::exists('components.auth.register-modal')
            || class_exists(\App\View\Components\Auth\RegisterModal::class);
    @endphp
    @if($hasLoginModal)
        <x-auth.login-modal />
    @endif
    @if($hasRegisterModal)
        <x-auth.register-modal />
    @endif

    {{-- ===== Script: sync hora servidor ===== --}}
    <script>
        (function () {
            const meta = document.querySelector('meta[name="server-now-ms"]');
            const serverNowMs = Number(meta?.content || '0');
            const readAt = Date.now();
            const skewMs = serverNowMs ? (serverNowMs - readAt) : 0;
            window.mesasNowMs = function () { return Date.now() + skewMs; };
        })();
    </script>

    {{-- ===== Hooks para abrir modales por data-attrs o query ===== --}}
    <script>
        (function () {
            function openAuth(which) { document.dispatchEvent(new CustomEvent('auth:open', { detail: { type: which } })); }
            document.querySelectorAll('[data-login-open]').forEach(a => {
                a.addEventListener('click', (e) => { e.preventDefault(); openAuth('login'); }, { passive: false });
            });
            document.querySelectorAll('[data-register-open]').forEach(a => {
                a.addEventListener('click', (e) => { e.preventDefault(); openAuth('register'); }, { passive: false });
            });
            try {
                const q = new URLSearchParams(location.search);
                if (q.get('login') === '1') openAuth('login');
                if (q.get('register') === '1') openAuth('register');
            } catch (_) { }
        })();
    </script>

    @stack('scripts')
</body>

</html>