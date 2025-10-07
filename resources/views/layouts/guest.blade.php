{{-- resources/views/components/guest-layout.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @php
        use Illuminate\Support\Facades\Route as LRoute;
        use Illuminate\Support\Str;

        // === Props del componente (atributos HTML) ===
        $title = $attributes->get('title', config('app.name', 'La Taberna'));
        $description = $attributes->get('description');
        $canonical = $attributes->get('canonical');
        $image = $attributes->get('image', asset('images/og-default.png'));
        $bodyClass = trim((string) $attributes->get('body-class', ''));
        $noindexRaw = $attributes->get('noindex', true);

        // Normaliza booleanos tipo "true"/"false"/1/0
        $noindex = filter_var($noindexRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $noindex = $noindex === null ? true : $noindex; // default true

        $appName = config('app.name', 'La Taberna');
        $fullTitle = $title ? ($title . ' · ' . $appName) : $appName;
        $descFinal = $description ? \Illuminate\Support\Str::limit(strip_tags($description), 160, '') : $appName;

        // Cache-busting para el logo (opcional, no rompe si falta el archivo)
        $logoPath = public_path('images/logo.png');
        $logoVer = file_exists($logoPath) ? filemtime($logoPath) : null;
        $logoHref = asset('images/logo.png') . ($logoVer ? ('?v=' . $logoVer) : '');

        // OG absolute URL
        $ogImage = Str::startsWith($image, ['http://', 'https://']) ? $image : url($image);

        // Fallback seguro a Home
        $homeUrl = LRoute::has('home') ? route('home') : url('/');
    @endphp

    <meta charset="utf-8">
    <title>{{ $fullTitle }}</title>
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <meta name="csrf-token"
          content="{{ csrf_token() }}">

    {{-- Color scheme y theme-color para barras del navegador --}}
    <meta name="color-scheme"
          content="light dark">
    <meta name="theme-color"
          content="#7B1E1E">

    @if($descFinal)
        <meta name="description"
              content="{{ $descFinal }}">
    @endif

    {{-- Robots según prop (default noindex) --}}
    <meta name="robots"
          content="{{ $noindex ? 'noindex,nofollow' : 'index,follow' }}">
    <meta name="googlebot"
          content="{{ $noindex ? 'noindex,nofollow' : 'index,follow' }}">

    <link rel="canonical"
          href="{{ $canonical ?: url()->current() }}">

    {{-- Hora de servidor en UTC ms (sync timers via window.mesasNowMs()) --}}
    <meta name="server-now-ms"
          content="{{ now('UTC')->valueOf() }}">

    {{-- Open Graph / Twitter --}}
    <meta property="og:type"
          content="website">
    <meta property="og:title"
          content="{{ $fullTitle }}">
    <meta property="og:description"
          content="{{ $descFinal }}">
    <meta property="og:url"
          content="{{ url()->current() }}">
    <meta property="og:image"
          content="{{ $ogImage }}">
    <meta name="twitter:card"
          content="summary_large_image">
    <meta name="twitter:title"
          content="{{ $fullTitle }}">
    <meta name="twitter:description"
          content="{{ $descFinal }}">
    <meta name="twitter:image"
          content="{{ $ogImage }}">

    {{-- Fuente (opcional) --}}
    <link rel="preconnect"
          href="https://fonts.googleapis.com">
    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    {{-- Icono de sitio (si existe) --}}
    @php $favicon = public_path('favicon.ico'); @endphp
    @if(file_exists($favicon))
        <link rel="icon"
              href="{{ asset('favicon.ico') }}">
    @endif

    <style>
        :root {
            --bg: #F2EFEA;
            --card: #FAF7F1;
            --ink: #26211E;
            --muted: #57514D;
            --maroon: #7B1E1E;
            --gold: #C8A24C;
            --line: #D9CFC3;
            --focus: #2E6FEA33;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1220;
                --card: #0f172a;
                --ink: #e5e7eb;
                --muted: #a1a1aa;
                --maroon: #f2c6c6;
                --gold: #e7c05c;
                --line: rgba(148, 163, 184, .28);
                --focus: #2563eb55;
            }
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.65 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
            -webkit-text-size-adjust: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(.75rem, 4vw, 2rem);
        }

        a {
            color: var(--maroon);
            text-decoration: none;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        textarea:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px;
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

        /* Skip-link visible al foco */
        .skiplink {
            position: absolute;
            left: -9999px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .skiplink:focus {
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

        .wrap {
            width: 100%;
            max-width: 440px;
            margin-inline: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            margin-bottom: .9rem;
            color: var(--maroon);
        }

        .brand img {
            width: 64px;
            height: 64px;
            display: block;
        }

        .brand span {
            font-weight: 700;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: .5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
            padding: clamp(.9rem, 3vw, 1.25rem);
        }

        .flash {
            padding: .65rem .8rem;
            border-radius: .5rem;
            margin: .5rem 0;
            background: #E9F7EF;
            border: 1px solid #BFE6CA;
        }

        .flash-error {
            background: #FCECEC;
            border-color: #F3B9B9;
        }

        .muted {
            color: var(--muted);
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
            border-radius: .5rem;
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

        @media (prefers-color-scheme: dark) {
            .form-control {
                background: #0b1220;
            }
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
            border-radius: .25rem;
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
            border-radius: .5rem;
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

        .form-error li+li {
            margin-top: .25rem;
        }
    </style>

    {{-- Head extra específico por página guest (si lo necesitás) --}}
    @stack('head')
</head>

<body class="{{ $bodyClass }}">
    <a href="#main"
       class="skiplink">{{ __('Saltar al contenido') }}</a>

    <div class="wrap"
         role="main"
         id="main"
         tabindex="-1"
         aria-label="{{ __('Contenido principal') }}">
        <a class="brand"
           href="{{ $homeUrl }}"
           aria-label="{{ __('Ir al inicio') }}">
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