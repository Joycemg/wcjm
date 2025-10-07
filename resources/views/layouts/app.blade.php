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
            --muted: #6b7280;
            --maroon: #7b2d26;
            --border: #e5e7eb;
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

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 1rem
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid var(--border);
            border-radius: .75rem;
            padding: .5rem .9rem
        }

        .btn.gold {
            background: #fef3c7;
            border-color: #f59e0b
        }

        .btn.red {
            background: #fee2e2;
            border-color: #ef4444
        }

        .btn.green {
            background: #dcfce7;
            border-color: #22c55e
        }

        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1rem
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
    <header class="border-b bg-white">
        <div class="container flex items-center justify-between">
            <a href="{{ route('home') }}"
               class="font-bold text-lg">{{ config('app.name', 'La Taberna') }}</a>
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

    <main class="container">
        @if (session('ok'))
            <div class="card mb-3 bg-green-50 border-green-300">{{ session('ok') }}</div>
        @endif
        @if (session('err'))
            <div class="card mb-3 bg-red-50 border-red-300">{{ session('err') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t mt-8 py-6 text-center text-sm text-gray-500 bg-white">
        © {{ date('Y') }} {{ config('app.name', 'La Taberna') }}
    </footer>

    @stack('scripts')
</body>

</html>