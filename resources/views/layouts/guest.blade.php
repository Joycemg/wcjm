{{-- resources/views/components/guest-layout.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <title>{{ config('app.name', 'La Taberna') }}</title>
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <meta name="csrf-token"
          content="{{ csrf_token() }}">

    {{-- (Opcional) Hora del servidor en UTC ms si querés usar window.mesasNowMs() en páginas guest --}}
    <meta name="server-now-ms"
          content="{{ now('UTC')->valueOf() }}">

    <style>
        :root {
            --bg: #F6EADF;
            --card: #FFF7EE;
            --ink: #2E2724;
            --muted: #6F655E;
            --maroon: #7B1E1E;
            --gold: #C8A24C;
            --line: #E2D3C2;
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
            -webkit-text-size-adjust: 100%;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: clamp(.75rem, 4vw, 2rem);
        }

        a {
            color: var(--maroon);
            text-decoration: none
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        textarea:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px
        }

        .wrap {
            width: 100%;
            max-width: 440px;
            margin-inline: auto
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            margin-bottom: .9rem;
            color: var(--maroon)
        }

        .brand img {
            width: 64px;
            height: 64px;
            display: block
        }

        .brand span {
            font-weight: 700;
            letter-spacing: .2px;
            white-space: nowrap
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 1rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
            padding: clamp(.9rem, 3vw, 1.25rem)
        }

        .flash {
            padding: .65rem .8rem;
            border-radius: .6rem;
            margin: .5rem 0;
            background: #E9F7EF;
            border: 1px solid #BFE6CA
        }

        .flash-error {
            background: #FCECEC;
            border-color: #F3B9B9
        }

        .muted {
            color: var(--muted)
        }
    </style>

    {{-- Head específico por página guest (si lo necesitás) --}}
    @stack('head')
</head>

<body>
    <div class="wrap"
         role="main">
        <a class="brand"
           href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}"
                 alt="{{ config('app.name', 'La Taberna') }}"
                 loading="lazy"
                 decoding="async">
            <span>{{ config('app.name', 'La Taberna') }}</span>
        </a>

        {{-- Mensajes genéricos opcionales (status/ok/err) --}}
        @if(session('ok'))
            <div class="flash"
                 role="status"
                 aria-live="polite">{{ session('ok') }}</div>
        @endif
        @if(session('err'))
            <div class="flash flash-error"
                 role="alert">{{ session('err') }}</div>
        @endif
        @if (session('status'))
            <div class="flash"
                 role="status"
                 aria-live="polite">{{ session('status') }}</div>
        @endif

        <div class="card">
            {{ $slot }}
        </div>
    </div>

    {{-- Script global opcional: expone window.mesasNowMs() usando la meta server-now-ms --}}
    <script>
        (function () {
            const meta = document.querySelector('meta[name="server-now-ms"]');
            const serverNowMs = Number(meta?.content || '0');
            const readAt = Date.now();
            const skewMs = serverNowMs ? (serverNowMs - readAt) : 0;
            window.mesasNowMs = function () { return Date.now() + skewMs; };
        })();
    </script>

    {{-- Scripts específicos por página guest --}}
    @stack('scripts')
</body>

</html>