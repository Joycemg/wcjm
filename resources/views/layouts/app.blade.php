{{-- resources/views/layouts/app.blade.php --}}
{{-- Layout base accesible, con fallbacks y dark mode --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="h-full">

<head>
    @php
        use Illuminate\Support\Facades\Route as LRoute;

        $appName = config('app.name', 'La Taberna');

        if (!isset($hasViteBuild)) {
            $viteManifest = public_path('build/manifest.json');
            $viteHot = public_path('hot');
            $hasViteBuild = file_exists($viteManifest) || file_exists($viteHot);
        }

        // Rutas con fallback seguro
        $homeUrl = LRoute::has('home') ? route('home') : url('/');
        $mesasIndexUrl = LRoute::has('mesas.index') ? route('mesas.index') : url('/mesas');
        $mesasCreateUrl = LRoute::has('mesas.create') ? route('mesas.create') : url('/mesas/create');
        $dashboardUrl = LRoute::has('dashboard') ? route('dashboard') : url('/panel');
        $loginUrl = LRoute::has('login') ? route('login') : url('/login');
        $registerUrl = LRoute::has('register') ? route('register') : url('/register');

        // Nav activo simple
        $isMesas = request()->routeIs('mesas.*');
        $isPanel = request()->routeIs('dashboard');
        $isLogin = request()->routeIs('login');
        $isRegister = request()->routeIs('register');
    @endphp

    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <meta name="csrf-token"
          content="{{ csrf_token() }}">
    <meta name="server-now-ms"
          content="{{ now('UTC')->valueOf() }}">
    <meta name="color-scheme"
          content="light dark">
    <meta name="theme-color"
          content="#7b1e1e">
    <title>@yield('title', $appName)</title>

    {{-- Fuente (opcional) --}}
    <link rel="preconnect"
          href="https://fonts.googleapis.com">
    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap"
          rel="stylesheet">

    {{-- CSS base propio (si lo usás) --}}
    <link rel="stylesheet"
          href="{{ asset('css/base.css') }}">

    @if ($hasViteBuild)
        {{-- Usa assets generados por Vite si existen --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- Fallback liviano para hosting sin Node/Vite --}}
        <link rel="stylesheet"
              href="{{ asset('css/app.css') }}">
        <script src="{{ asset('js/app.js') }}"
                defer></script>
    @endif

    @stack('head')

    <style>
        /* =================== Tokens =================== */
        :root {
            --bg: #f4f4f5;
            --card: #ffffff;
            --ink: #111827;
            --muted: #4b5563;
            --line: #d4d4d8;
            --accent: #7b1e1e;
            --accent-ink: #f9fafb;
            --focus: #2563eb33;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111827;
                --card: #1f2937;
                --ink: #f3f4f6;
                --muted: #9ca3af;
                --line: #374151;
                --accent: #f97316;
                --accent-ink: #0f172a;
                --focus: #93c5fd33;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }

        /* =================== Base =================== */
        html,
        body {
            height: 100%;
        }

        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, Apple Color Emoji, Segoe UI Emoji;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.6;
            margin: 0;
        }

        .page-body {
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1100px;
            margin-inline: auto;
            padding: clamp(1rem, 3vw, 1.6rem);
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
            border: 0;
        }

        .skip-link {
            position: absolute;
            left: -9999px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .skip-link:focus {
            left: 1rem;
            top: 1rem;
            width: auto;
            height: auto;
            background: var(--card);
            color: var(--ink);
            padding: .5rem .75rem;
            border: 1px solid var(--line);
        }

        /* =================== Botones base =================== */
        :where(a.btn, button.btn) {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            text-decoration: none;
            cursor: pointer;
            padding: .6rem .9rem;
            border-radius: 0;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--ink);
            font-weight: 600;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease;
        }

        :where(a.btn, button.btn):hover {
            background: #e4e4e7;
        }

        :where(a.btn, button.btn):focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px;
        }

        /* =================== Header =================== */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--accent);
            color: var(--accent-ink);
            border-bottom: 1px solid var(--line);
        }

        .site-header .container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: .75rem;
            color: inherit;
            text-decoration: none;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 0;
            background: var(--accent-ink);
            color: var(--accent);
            font-size: 1.4rem;
        }

        .app-title {
            font-size: clamp(1.3rem, 3vw, 1.6rem);
        }

        .main-nav {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .site-header .btn {
            background: transparent;
            border-color: rgba(249, 250, 251, .45);
            color: var(--accent-ink);
        }

        .site-header .btn:hover {
            background: rgba(249, 250, 251, .12);
        }

        .site-header .btn.active {
            background: var(--accent-ink);
            color: var(--accent);
        }

        /* =================== Main =================== */
        .site-main {
            flex: 1;
            padding: clamp(2rem, 5vw, 3rem) 0 clamp(2.6rem, 6vw, 4rem);
        }

        .site-main .container {
            display: grid;
            gap: clamp(1rem, 3vw, 1.8rem);
            background: var(--card);
            border: 1px solid var(--line);
        }

        /* =================== Flash =================== */
        .flash {
            padding: .9rem 1.1rem;
            border-radius: 0;
            border: 1px solid var(--line);
            background: var(--card);
            font-weight: 600;
        }

        .flash-ok {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }

        .flash-err {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #7f1d1d;
        }

        @media (prefers-color-scheme: dark) {
            .flash-ok {
                background: rgba(34, 197, 94, .2);
                border-color: rgba(34, 197, 94, .4);
                color: #bbf7d0;
            }

            .flash-err {
                background: rgba(248, 113, 113, .2);
                border-color: rgba(248, 113, 113, .4);
                color: #fecaca;
            }
        }

        /* =================== Footer =================== */
        .site-footer {
            background: #e4e4e7;
            color: var(--muted);
            text-align: center;
            padding: clamp(1.6rem, 3vw, 2rem) 1rem;
            border-top: 1px solid var(--line);
            font-size: .95rem;
        }

        .site-footer a {
            color: inherit;
            text-decoration: underline;
        }

        /* =================== Responsivo =================== */
        @media (max-width:640px) {
            .site-header .container {
                flex-direction: column;
                align-items: flex-start;
            }

            .main-nav {
                width: 100%;
            }

            .site-main {
                padding-top: clamp(1.5rem, 6vw, 2rem);
            }
        }
    </style>

    {{-- Helper JS para hora del servidor (usado por vistas) --}}
    <script>
        (function () {
            const meta = document.querySelector('meta[name="server-now-ms"]');
            const serverNowMs = meta ? parseInt(meta.content, 10) : Date.now();
            const skew = serverNowMs - Date.now();
            window.mesasNowMs = function () { return Date.now() + skew; };
        })();
    </script>
</head>

<body class="page-body">
    {{-- Accesibilidad: saltar al contenido --}}
    <a href="#main"
       class="skip-link">{{ __('Saltar al contenido') }}</a>

    <header class="site-header"
            role="banner">
        <div class="container">
            <a href="{{ $homeUrl }}"
               class="brand"
               aria-label="{{ $appName }}">
                <span class="brand-badge"
                      aria-hidden="true">🍷</span>
                <span class="app-title">{{ $appName }}</span>
            </a>

            <nav class="main-nav"
                 aria-label="{{ __('Principal') }}">
                <a class="btn {{ $isMesas ? 'active' : '' }}"
                   href="{{ $mesasIndexUrl }}"
                   {{ $isMesas ? 'aria-current=page' : '' }}>
                    {{ __('Mesas') }}
                </a>

                @can('manage-tables')
                    <a class="btn gold"
                       href="{{ $mesasCreateUrl }}">➕ {{ __('Nueva mesa') }}</a>
                @endcan

                @auth
                    <a class="btn {{ $isPanel ? 'active' : '' }}"
                       href="{{ $dashboardUrl }}"
                       {{ $isPanel ? 'aria-current=page' : '' }}>
                        {{ __('Panel') }}
                    </a>
                @else
                    <a class="btn {{ $isLogin ? 'active' : '' }}"
                       href="{{ $loginUrl }}"
                       {{ $isLogin ? 'aria-current=page' : '' }}>
                        {{ __('Entrar') }}
                    </a>
                    <a class="btn {{ $isRegister ? 'active' : '' }}"
                       href="{{ $registerUrl }}"
                       {{ $isRegister ? 'aria-current=page' : '' }}>
                        {{ __('Crear cuenta') }}
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main"
          class="site-main"
          role="main">
        <div class="container">
            @if (session('ok'))
                <div class="flash flash-ok"
                     role="status">{{ session('ok') }}</div>
            @endif
            @if (session('err'))
                <div class="flash flash-err"
                     role="alert">{{ session('err') }}</div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="site-footer"
            role="contentinfo">
        © {{ date('Y') }} {{ $appName }} · {{ __('Comunidad de Juegos de Mesa') }}
    </footer>

    @stack('scripts')
</body>

</html>