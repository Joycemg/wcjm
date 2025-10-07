{{-- resources/views/components/guest-layout.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @php
        // === Props del componente (atributos HTML) ===
        $title = $attributes->get('title', config('app.name', 'La Taberna'));
        $description = $attributes->get('description');
        $canonical = $attributes->get('canonical');
        $image = $attributes->get('image', asset('images/og-default.png'));
        $bodyClass = trim((string) $attributes->get('body-class', ''));
        $noindexRaw = $attributes->get('noindex', true);
        $noindex = filter_var($noindexRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $noindex = $noindex === null ? true : $noindex; // default true

        $appName = config('app.name', 'La Taberna');
        $fullTitle = $title ? ($title . ' · ' . $appName) : $appName;
        $descFinal = $description ? \Illuminate\Support\Str::limit(strip_tags($description), 160, '') : $appName;

        // Cache-busting para el logo (opcional, no rompe si falta el archivo)
        $logoPath = public_path('images/logo.png');
        $logoVer = file_exists($logoPath) ? filemtime($logoPath) : null;
        $logoHref = asset('images/logo.png') . ($logoVer ? ('?v=' . $logoVer) : '');
    @endphp

    <meta charset="utf-8">
    <title>{{ $fullTitle }}</title>
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <meta name="csrf-token"
          content="{{ csrf_token() }}">
    <meta name="color-scheme"
          content="light">
    @if($descFinal)
        <meta name="description"
      content="{{ $descFinal }}">@endif
    <meta name="robots"
          content="{{ $noindex ? 'noindex,nofollow' : 'index,follow' }}">
    <link rel="canonical"
          href="{{ $canonical ?: url()->current() }}">

    {{-- Hora del servidor en UTC ms (para sync de timers si querés usar window.mesasNowMs()) --}}
    <meta name="server-now-ms"
          content="{{ now('UTC')->valueOf() }}">

    {{-- Open Graph / Twitter (estático y liviano) --}}
    <meta property="og:type"
          content="website">
    <meta property="og:title"
          content="{{ $fullTitle }}">
    <meta property="og:description"
          content="{{ $descFinal }}">
    <meta property="og:url"
          content="{{ url()->current() }}">
    <meta property="og:image"
          content="{{ \Illuminate\Support\Str::startsWith($image, ['http://', 'https://']) ? $image : url($image) }}">
    <meta name="twitter:card"
          content="summary_large_image">
    <meta name="twitter:title"
          content="{{ $fullTitle }}">
    <meta name="twitter:description"
          content="{{ $descFinal }}">
    <meta name="twitter:image"
          content="{{ \Illuminate\Support\Str::startsWith($image, ['http://', 'https://']) ? $image : url($image) }}">

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
            text-align: center
        }

        .auth-title {
            margin: 0;
            color: var(--maroon);
            font-size: clamp(1.35rem, 4vw, 1.75rem);
            font-weight: 700
        }

        .form-grid {
            display: grid;
            gap: 1rem
        }

        .form-field {
            display: grid;
            gap: .35rem
        }

        .form-label {
            font-weight: 600;
            font-size: .95rem;
            color: var(--muted)
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
            box-shadow: 0 0 0 3px var(--focus)
        }

        .password-wrap {
            position: relative
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
            outline-offset: 2px
        }

        .form-hint {
            font-size: .85rem;
            color: var(--muted);
            margin-top: .25rem
        }

        .form-hint-warning {
            color: #92400e
        }

        .is-hidden {
            display: none !important
        }

        .form-check label {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .95rem;
            color: var(--muted)
        }

        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border: 1px solid var(--line);
            border-radius: .35rem;
            accent-color: var(--maroon)
        }

        .form-check input[type="checkbox"]:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: .25rem
        }

        .form-actions-end {
            display: inline-flex;
            gap: .6rem;
            align-items: center;
            flex-wrap: wrap
        }

        .form-links {
            display: flex;
            flex-direction: column;
            gap: .35rem
        }

        .form-link {
            color: var(--maroon);
            text-decoration: underline;
            font-size: .9rem
        }

        .form-alert {
            margin-bottom: 1rem;
            padding: .75rem .9rem;
            border-radius: .75rem;
            border: 1px solid var(--line);
            background: #fff
        }

        .form-alert ul {
            margin: .35rem 0 0 1.1rem;
            padding: 0
        }

        .form-alert-title {
            display: block;
            font-weight: 600;
            margin-bottom: .35rem
        }

        .form-alert-info {
            background: #E9F7EF;
            border-color: #BFE6CA;
            color: #166534
        }

        .form-alert-error {
            background: #FCECEC;
            border-color: #F3B9B9;
            color: #7f1d1d
        }

        .form-status {
            font-size: .9rem
        }

        .form-error {
            margin: .2rem 0 0;
            color: #7f1d1d;
            font-size: .85rem;
            list-style: disc;
            padding-left: 1.1rem
        }

        .form-error li+li {
            margin-top: .25rem
        }
    </style>

    {{-- Head extra específico por página guest (si lo necesitás) --}}
    @stack('head')
</head>

<body class="{{ $bodyClass }}">
    <a href="#main"
       class="sr-only">Saltar al contenido</a>

    <div class="wrap"
         role="main"
         id="main"
         tabindex="-1"
         aria-label="Contenido principal">
        <a class="brand"
           href="{{ route('home') }}"
           aria-label="Ir al inicio">
            <img src="{{ $logoHref }}"
                 alt="{{ $appName }}"
                 loading="lazy"
                 decoding="async"
                 width="64"
                 height="64">
            <span>{{ $appName }}</span>
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