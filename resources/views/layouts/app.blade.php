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
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&family=Righteous&display=swap" rel="stylesheet">

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
        :root {
            --muted: #4c566a;
            --maroon: #b3472d;
            --border: #d8dee9;
            --night: #1f2937;
            --amber: #fbbf24;
            --emerald: #34d399;
        }

        .muted {
            color: var(--muted)
        }

        .pill {
            display: inline-block;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: .125rem .5rem;
            font-size: .75rem
        }

        body {
            font-family: 'Nunito Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background:
                radial-gradient(circle at 0% 0%, rgba(255, 255, 255, 0.25) 0, rgba(255, 255, 255, 0) 60%),
                radial-gradient(circle at 100% 0%, rgba(255, 255, 255, 0.25) 0, rgba(255, 255, 255, 0) 60%),
                linear-gradient(135deg, #0f172a 0%, #1e293b 35%, #273549 70%, #2f3f57 100%);
            color: #1f2937;
            min-height: 100vh;
        }

        .app-title {
            font-family: 'Righteous', cursive;
            letter-spacing: .02em;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.25rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border-radius: 999px;
            padding: .55rem 1.1rem;
            font-weight: 600;
            letter-spacing: .01em;
            background: linear-gradient(135deg, rgba(255,255,255,.12), rgba(255,255,255,0));
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.25);
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.3);
            background: linear-gradient(135deg, rgba(255,255,255,.24), rgba(255,255,255,0.05));
        }

        .btn.gold {
            background: linear-gradient(135deg, #fbbf24, #f97316);
            border-color: rgba(251, 191, 36, .8);
            color: #1f2937;
            box-shadow: 0 8px 18px rgba(251, 191, 36, 0.35);
        }

        .btn.gold:hover {
            background: linear-gradient(135deg, #facc15, #fb923c);
        }

        .btn.red {
            background: linear-gradient(135deg, #f87171, #ef4444);
            border-color: rgba(239, 68, 68, .85);
            box-shadow: 0 8px 18px rgba(248, 113, 113, 0.35);
            color: #fff;
        }

        .btn.green {
            background: linear-gradient(135deg, #34d399, #059669);
            border-color: rgba(5, 150, 105, .75);
            box-shadow: 0 8px 18px rgba(52, 211, 153, 0.35);
            color: #032b1f;
        }

        .card {
            background: linear-gradient(145deg, rgba(255,255,255,.95), rgba(255,255,255,.85));
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 1.25rem;
            padding: 1.25rem;
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.22);
            backdrop-filter: blur(6px);
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            object-fit: cover
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem
        }

        @media (min-width: 900px) {
            .grid-2 {
                grid-template-columns: 2fr 1fr;
            }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">
    <header class="border-b" style="border-color: rgba(255,255,255,.08); background: linear-gradient(135deg, rgba(15,23,42,.85), rgba(30,41,59,.85)); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 20;">
        <div class="container flex items-center justify-between" style="gap: 1.5rem;">
            <a href="{{ route('home') }}"
               class="font-bold text-lg app-title text-white flex items-center gap-2">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#fbbf24,#f97316);color:#111827;box-shadow:0 8px 18px rgba(251,191,36,.35);">🎲</span>
                {{ config('app.name', 'La Taberna') }}
            </a>
            <nav class="flex items-center gap-2">
                <a class="btn"
                   href="{{ route('mesas.index') }}">Mesas</a>

                @can('manage-tables')
                    @if(\Illuminate\Support\Facades\Route::has('mesas.create'))
                        <a class="btn gold"
                           href="{{ route('mesas.create') }}">➕ Nueva mesa</a>
                    @endif
                @endcan

                @auth
                    <a class="btn"
                       href="{{ route('dashboard') }}">Panel</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="container" style="padding-top: 2.5rem; padding-bottom: 3rem;">
        @if (session('ok'))
            <div class="card mb-3 bg-green-50 border-green-300">{{ session('ok') }}</div>
        @endif
        @if (session('err'))
            <div class="card mb-3 bg-red-50 border-red-300">{{ session('err') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-8 py-6 text-center text-sm text-gray-200" style="border-top: 1px solid rgba(255,255,255,.1); background: linear-gradient(135deg, rgba(15,23,42,.85), rgba(30,41,59,.85));">
        © {{ date('Y') }} {{ config('app.name', 'La Taberna') }} · Comunidad de Juegos de Mesa
    </footer>

    @stack('scripts')
</body>

</html>