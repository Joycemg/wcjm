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
        /* =================== Design tokens / modo oscuro =================== */
        :root {
            --bg: #faf7f1;
            --card: #fff;
            --ink: #1f2937;
            --muted: #6b7280;
            --line: #e5e7eb;
            --maroon: #7b1e1e;
            --gold: #d7ae4f;
            --gold-press: #b8923e;
            --shadow-sm: 0 6px 16px rgba(0, 0, 0, .08);
            --shadow-lg: 0 18px 40px rgba(123, 30, 30, .22);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1220;
                --card: #0f172a;
                --ink: #e5e7eb;
                --muted: #a1a1aa;
                --line: rgba(148, 163, 184, .28);
                --maroon: #f2c6c6;
                --gold: #e7c05c;
                --gold-press: #d9ad38;
                --shadow-sm: 0 6px 16px rgba(0, 0, 0, .35);
                --shadow-lg: 0 18px 40px rgba(0, 0, 0, .45);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
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
            background-image:
                radial-gradient(circle at 0% 0%, rgba(200, 162, 76, .12), transparent 55%),
                radial-gradient(circle at 100% 0%, rgba(123, 30, 30, .08), transparent 55%),
                linear-gradient(180deg, rgba(250, 247, 241, .92), rgba(242, 239, 234, .96));
            background-attachment: fixed;
            color: var(--ink);
            line-height: 1.65;
            margin: 0;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background-image:
                    radial-gradient(circle at 0% 0%, rgba(200, 162, 76, .08), transparent 55%),
                    radial-gradient(circle at 100% 0%, rgba(123, 30, 30, .10), transparent 55%),
                    linear-gradient(180deg, rgba(10, 14, 24, .92), rgba(12, 16, 26, .96));
            }
        }

        .page-body {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .page-body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(120deg, rgba(200, 162, 76, .08), transparent 60%),
                linear-gradient(300deg, rgba(123, 30, 30, .07), transparent 65%);
            opacity: .55;
            mix-blend-mode: multiply;
            z-index: 0;
        }

        .page-body>* {
            position: relative;
            z-index: 1;
        }

        .container {
            max-width: 1100px;
            margin-inline: auto;
            padding: clamp(1rem, 3.2vw, 1.6rem);
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

        /* Skip-link visible al enfocar (sin Tailwind) */
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
            background: #fff;
            color: #000;
            padding: .5rem .75rem;
            border-radius: .5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .2);
        }

        /* =================== Botones base =================== */
        :where(a.btn, button.btn) {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            text-decoration: none;
            cursor: pointer;
            padding: .6rem .9rem;
            border-radius: .5rem;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--ink);
            font-weight: 600;
        }

        :where(a.btn, button.btn):focus-visible {
            outline: 3px solid #60a5fa;
            outline-offset: 2px;
        }

        /* =================== Header =================== */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: linear-gradient(135deg, rgba(123, 30, 30, .96), rgba(103, 23, 23, .96));
            color: #fff7ee;
            border-bottom: 1px solid rgba(255, 247, 238, .28);
            box-shadow: 0 18px 40px rgba(123, 30, 30, .32);
        }

        .site-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top left, rgba(255, 247, 238, .25), transparent 55%);
            opacity: .8;
            pointer-events: none;
        }

        .site-header .container {
            display: flex;
            align-items: center;
            gap: clamp(.75rem, 3vw, 1.5rem);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: .75rem;
            color: inherit;
            text-decoration: none;
            font-weight: 800;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: .4rem;
            background: linear-gradient(135deg, #f4d98a, #d7ae4f);
            color: #471010;
            box-shadow: 0 12px 20px rgba(0, 0, 0, .18);
            font-size: 1.55rem;
        }

        .app-title {
            font-size: clamp(1.35rem, 3vw, 1.7rem);
            color: #fff7ee;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .main-nav {
            display: flex;
            align-items: center;
            gap: .6rem;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .site-header .btn {
            background: rgba(255, 255, 255, .14);
            border-color: rgba(255, 255, 255, .32);
            color: #fff7ee;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .18);
            transition: background .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        .site-header .btn:hover {
            background: rgba(255, 255, 255, .22);
            box-shadow: 0 14px 30px rgba(0, 0, 0, .22);
            transform: translateY(-1px);
        }

        .site-header .btn.gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-press));
            border-color: transparent;
            color: #1f2937;
            box-shadow: 0 14px 32px rgba(200, 162, 76, .36);
        }

        .site-header .btn.gold:hover {
            transform: translateY(-1px);
        }

        .site-header .btn.active {
            box-shadow: 0 0 0 2px rgba(255, 255, 255, .6) inset;
        }

        /* =================== Main =================== */
        .site-main {
            flex: 1;
            padding: clamp(2rem, 5vw, 3rem) 0 clamp(2.6rem, 6vw, 4rem);
        }

        .site-main .container {
            position: relative;
            display: grid;
            gap: clamp(1rem, 3vw, 1.8rem);
            background: linear-gradient(180deg, rgba(250, 247, 241, .94), rgba(242, 239, 234, .9));
            border: 1px solid rgba(217, 207, 195, .65);
            border-radius: .5rem;
            box-shadow: var(--shadow-lg);
        }

        .site-main .container::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(200, 162, 76, .08), rgba(255, 255, 255, 0) 55%);
            opacity: .75;
            pointer-events: none;
        }

        .site-main .container>* {
            position: relative;
            z-index: 1;
        }

        /* =================== Flash =================== */
        .flash {
            padding: .9rem 1.1rem;
            border-radius: .5rem;
            border: 1px solid var(--line);
            background: var(--card);
            box-shadow: var(--shadow-sm);
            font-weight: 600;
        }

        .flash-ok {
            background: #e9f7ef;
            border-color: #bfe6ca;
            color: #165534;
        }

        .flash-err {
            background: #fcecec;
            border-color: #f3b9b9;
            color: #7b1e1e;
        }

        @media (prefers-color-scheme: dark) {
            .flash-ok {
                background: rgba(16, 185, 129, .15);
                border-color: rgba(16, 185, 129, .35);
                color: #a7f3d0;
            }

            .flash-err {
                background: rgba(239, 68, 68, .15);
                border-color: rgba(239, 68, 68, .35);
                color: #fecaca;
            }
        }

        /* =================== Footer =================== */
        .site-footer {
            background: linear-gradient(135deg, rgba(123, 30, 30, .96), rgba(103, 23, 23, .96));
            color: #f8ede0;
            text-align: center;
            padding: clamp(1.6rem, 3vw, 2rem) 1rem;
            border-top: 1px solid rgba(255, 247, 238, .25);
            font-size: .95rem;
            letter-spacing: .03em;
            box-shadow: 0 -12px 28px rgba(38, 33, 30, .16);
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