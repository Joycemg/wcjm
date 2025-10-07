{{-- resources/views/layouts/app.blade.php --}}
{{-- Layout base minimalista con Tailwind y slots --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @php
        $appName = config('app.name', 'La Taberna');

        if (! isset($hasViteBuild)) {
            $viteManifest = public_path('build/manifest.json');
            $viteHot = public_path('hot');
            $hasViteBuild = file_exists($viteManifest) || file_exists($viteHot);
        }
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="server-now-ms" content="{{ now('UTC')->valueOf() }}">
    <title>@yield('title', $appName)</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/base.css') }}">

    @if ($hasViteBuild)
        {{-- Usa assets generados por Vite si existen --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- Fallback liviano para hosting compartido (sin Node/Vite) --}}
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <script src="{{ asset('js/app.js') }}" defer></script>
    @endif

    @stack('head')

    <style>
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
            background: var(--bg);
            background-image:
                radial-gradient(circle at 0% 0%, rgba(200, 162, 76, .12), transparent 55%),
                radial-gradient(circle at 100% 0%, rgba(123, 30, 30, .08), transparent 55%),
                linear-gradient(180deg, rgba(250, 247, 241, .92), rgba(242, 239, 234, .96));
            background-attachment: fixed;
            color: var(--ink);
            min-height: 100vh;
            line-height: 1.65;
        }

        .page-body {
            min-height: 100vh;
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

        .page-body > * {
            position: relative;
            z-index: 1;
        }

        h1,
        h2,
        h3,
        .app-title {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
            color: var(--maroon);
            font-weight: 700;
            letter-spacing: .015em;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: clamp(1rem, 3.2vw, 1.6rem);
        }

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
            font-weight: 700;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 0;
            background: linear-gradient(135deg, #f4d98a, #d7ae4f);
            color: var(--maroon);
            box-shadow: 0 12px 20px rgba(0, 0, 0, .18);
            font-size: 1.55rem;
        }

        .app-title {
            font-size: clamp(1.4rem, 3vw, 1.7rem);
            color: #fff7ee;
            text-transform: uppercase;
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
            font-weight: 600;
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
            color: var(--ink);
            box-shadow: 0 14px 32px rgba(200, 162, 76, .36);
        }

        .site-header .btn.gold:hover {
            transform: translateY(-1px);
        }

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
            border-radius: 0;
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

        .site-main .container > * {
            position: relative;
            z-index: 1;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(1.2rem, 4vw, 1.8rem);
        }

        @media (min-width: 900px) {
            .grid-2 {
                grid-template-columns: 2fr 1fr;
            }
        }

        .flash {
            padding: .9rem 1.1rem;
            border-radius: 0;
            border: 1px solid var(--line);
            background: #fff;
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
            color: var(--maroon);
        }

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

        @media (max-width: 640px) {
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
</head>

<body class="page-body">
    <header class="site-header" role="banner">
        <div class="container">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-badge">🍷</span>
                <span class="app-title">{{ config('app.name', 'La Taberna') }}</span>
            </a>
            <nav class="main-nav" aria-label="{{ __('Principal') }}">
                <a class="btn" href="{{ route('mesas.index') }}">Mesas</a>

                @can('manage-tables')
                    @if(\Illuminate\Support\Facades\Route::has('mesas.create'))
                        <a class="btn gold" href="{{ route('mesas.create') }}">➕ Nueva mesa</a>
                    @endif
                @endcan

                @auth
                    <a class="btn" href="{{ route('dashboard') }}">Panel</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container">
            @if (session('ok'))
                <div class="flash flash-ok" role="status">{{ session('ok') }}</div>
            @endif
            @if (session('err'))
                <div class="flash flash-err" role="alert">{{ session('err') }}</div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="site-footer">
        © {{ date('Y') }} {{ config('app.name', 'La Taberna') }} · Comunidad de Juegos de Mesa
    </footer>

    @stack('scripts')
</body>

</html>