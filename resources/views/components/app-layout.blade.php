{{-- resources/views/components/app-layout.blade.php --}}
@php
    $appName = config('app.name', 'La Taberna');
    $titleText = trim((string) ($title ?? ''));
    $headingText = trim((string) ($heading ?? ''));
    $pageTitle = $titleText !== ''
        ? ($titleText . ' · ' . $appName)
        : ($headingText !== '' ? ($headingText . ' · ' . $appName) : ($appName . ' · Rol & Juegos'));

    $metaDescription = $description ?? config('app.description');
    $canonicalUrl = $canonical ?? request()->fullUrl();
    $robots = $noindex ? 'noindex, nofollow' : 'index, follow';
    $imageUrl = $image
        ? (\Illuminate\Support\Str::startsWith($image, ['http://', 'https://', '//']) ? $image : asset($image))
        : null;

    $fluidClass = $fluid ? ' is-fluid' : '';
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <title>{{ $pageTitle }}</title>
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <meta name="csrf-token"
          content="{{ csrf_token() }}">
    <meta name="color-scheme"
          content="light">
    <meta name="robots"
          content="{{ $robots }}">
    @if($metaDescription)
        <meta name="description"
              content="{{ $metaDescription }}">
    @endif
    <meta name="server-now-ms"
          content="{{ now('UTC')->valueOf() }}">
    <link rel="canonical"
          href="{{ $canonicalUrl }}">

    {{-- Open Graph / Twitter --}}
    <meta property="og:type"
          content="website">
    <meta property="og:title"
          content="{{ $titleText !== '' ? $titleText : $appName }}">
    @if($metaDescription)
        <meta property="og:description"
              content="{{ $metaDescription }}">
    @endif
    <meta property="og:url"
          content="{{ $canonicalUrl }}">
    @if($imageUrl)
        <meta property="og:image"
              content="{{ $imageUrl }}">
    @endif

    <meta name="twitter:card"
          content="{{ $imageUrl ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title"
          content="{{ $titleText !== '' ? $titleText : $appName }}">
    @if($metaDescription)
        <meta name="twitter:description"
              content="{{ $metaDescription }}">
    @endif
    @if($imageUrl)
        <meta name="twitter:image"
              content="{{ $imageUrl }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F2EFEA;
            --card: #FAF7F1;
            --ink: #26211E;
            --muted: #57514D;
            --maroon: #7B1E1E;
            --gold: #C8A24C;
            --line: #D9CFC3;
            --maroon-press: #671717;
            --gold-press: #AD8C3D;
            --focus: #2E6FEA33;
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
            font: 16px/1.65 "Inter", "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif;
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
            border-radius: 0;
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
            border-radius: 0;
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
            border-radius: 0;
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

        .is-fluid.top {
            max-width: none;
            width: 100%;
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
            border-radius: 0;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--ink);
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            letter-spacing: .01em;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
            box-shadow: 0 6px 14px rgba(38, 33, 30, .08)
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

        main.is-fluid {
            max-width: none;
            width: 100%;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 0;
            padding: clamp(.75rem, 2.5vw, 1rem);
            box-shadow: 0 8px 16px rgba(38, 33, 30, .06)
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
            border-radius: 0;
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
            border-radius: 0
        }

        tbody tr td:first-child {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0
        }

        tbody tr td:last-child {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0
        }

        .cards {
            display: grid;
            gap: clamp(.75rem, 2.5vw, 1rem);
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr))
        }

        .card-game {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 0;
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
            border-radius: 0;
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
            border-radius: 0;
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
            border-radius: 0;
            margin: .7rem 0;
            background: #E9F7EF;
            border: 1px solid #BFE6CA
        }

        .flash.err {
            background: #FCECEC;
            border-color: #F3B9B9
        }

        .page-header {
            background: rgba(255, 255, 255, .55);
            border-bottom: 1px solid var(--line);
        }

        .page-header .inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: clamp(.75rem, 3vw, 1.25rem);
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: flex-end;
        }

        .page-header .inner.is-fluid {
            max-width: none;
            width: 100%;
        }

        .page-header h1 {
            margin: 0;
            color: var(--maroon);
            font-size: clamp(1.4rem, 3.5vw, 2rem);
        }

        .page-header .meta {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            flex: 1;
            min-width: 240px;
        }

        .page-header .actions-slot {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
        }
    </style>

    {{ $head ?? '' }}
    @stack('head')
</head>

<body>
    <a class="skip-link"
       href="#main">{{ __('Saltar al contenido') }}</a>

    @php
        $homeHref = Route::has('home') ? route('home') : url('/');
    @endphp

    <header role="banner">
        <div class="top{{ $fluidClass }}">
            <a class="brand"
               href="{{ $homeHref }}">
                <img src="{{ asset('images/logo.svg') }}"
                     alt="{{ $appName }}">
                <span>{{ $appName }}</span>
            </a>

            <div class="grow"></div>

            @auth
                @php
                    $avatar = auth()->user()->avatar_url ?? asset(config('auth.avatars.default', 'images/avatar-default.svg'));
                    $logoutAction = Route::has('logout') ? route('logout') : url('/logout');
                    $profileTarget = Route::has('profile.show')
                        ? route('profile.show', auth()->user()->profile_param ?? auth()->user())
                        : url('/profile');
                @endphp

                <a class="btn"
                   href="{{ $profileTarget }}"
                   style="gap:.6rem">
                    <img src="{{ $avatar }}"
                         alt="{{ __('Mi avatar') }}"
                         loading="lazy"
                         style="width:26px;height:26px;border-radius: 0;object-fit:cover;border:1px solid var(--line)">
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
                    $loginHref = Route::has('login') ? route('login') : url('/login');
                    $registerHref = Route::has('register') ? route('register') : url('/register');
                @endphp

                <a class="btn"
                   href="{{ $loginHref }}"
                   data-login-open>{{ __('Entrar') }}</a>

                @if (Route::has('register'))
                    <a class="btn"
                       href="{{ $registerHref }}"
                       data-register-open>{{ __('Registrarse') }}</a>
                @endif
            @endauth
        </div>
    </header>

    @if (!$hideHeader && ($headingText !== '' || !empty($breadcrumbs) || isset($header) || isset($actions)))
        <div class="page-header">
            <div class="inner{{ $fluidClass }}">
                <div class="meta">
                    @if(!empty($breadcrumbs))
                        <nav aria-label="{{ __('Migas de pan') }}"
                             class="muted"
                             style="font-size:.85rem;display:flex;flex-wrap:wrap;gap:.35rem;align-items:center">
                            @foreach($breadcrumbs as $index => $crumb)
                                @if($crumb['url'] && $index < count($breadcrumbs) - 1)
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                    <span aria-hidden="true">/</span>
                                @else
                                    <span aria-current="page"
                                          style="font-weight:600">{{ $crumb['label'] }}</span>
                                @endif
                            @endforeach
                        </nav>
                    @endif

                    @if($headingText !== '')
                        <h1>{{ $headingText }}</h1>
                    @elseif(isset($header))
                        {{ $header }}
                    @endif

                    @if(isset($subheading))
                        <div class="muted">{{ $subheading }}</div>
                    @endif
                </div>

                @isset($actions)
                    <div class="actions-slot">
                        {{ $actions }}
                    </div>
                @endisset
            </div>
        </div>
    @endif

    <main id="main"
          role="main"
          tabindex="-1"
          class="{{ $fluid ? 'is-fluid' : '' }}">
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

        {{ $slot }}
    </main>

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

    <footer style="margin:2rem auto 3rem;max-width:1100px;padding:0 clamp(.75rem,3vw,1rem);color:var(--muted);font-size:.85rem">
        <div>&copy; {{ date('Y') }} {{ $appName }}</div>
    </footer>

    <script>
        (function () {
            const meta = document.querySelector('meta[name="server-now-ms"]');
            const serverNowMs = Number(meta?.content || '0');
            const readAt = Date.now();
            const skewMs = serverNowMs ? (serverNowMs - readAt) : 0;
            window.mesasNowMs = function () { return Date.now() + skewMs; };
        })();
    </script>

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

    {{ $scripts ?? '' }}
    @stack('scripts')
</body>

</html>
