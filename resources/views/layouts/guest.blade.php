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

        .auth-header {
            margin-bottom: 1rem;
            text-align: center;
        }

        .auth-title {
            margin: 0;
            color: var(--maroon);
            font-size: clamp(1.35rem, 4vw, 1.75rem);
            font-weight: 700;
        }

        .form-grid {
            display: grid;
            gap: 1rem;
        }

        .form-field {
            display: grid;
            gap: .35rem;
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

        .password-wrap {
            position: relative;
        }

        .pass-toggle {
            position: absolute;
            inset-block-start: 50%;
            inset-inline-end: .55rem;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            padding: .25rem;
            border-radius: .5rem;
        }

        .pass-toggle:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px;
        }

        .form-hint {
            font-size: .85rem;
            color: var(--muted);
            margin-top: .25rem;
        }

        .form-hint-warning {
            color: #92400e;
        }

        .is-hidden {
            display: none !important;
        }

        .form-check label {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .95rem;
            color: var(--muted);
        }

        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border: 1px solid var(--line);
            border-radius: .35rem;
            accent-color: var(--maroon);
        }

        .form-check input[type="checkbox"]:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: .25rem;
        }

        .form-actions-end {
            display: inline-flex;
            gap: .6rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .form-links {
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }

        .form-link {
            color: var(--maroon);
            text-decoration: underline;
            font-size: .9rem;
        }

        .form-alert {
            margin-bottom: 1rem;
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
            margin: .2rem 0 0;
            color: #7f1d1d;
            font-size: .85rem;
            list-style: disc;
            padding-left: 1.1rem;
        }

        .form-error li + li {
            margin-top: .25rem;
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