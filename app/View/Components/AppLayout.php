@props([
// SEO
'title' => config('app.name'),
'description' => null,
'image' => null,
'canonical' => request()->fullUrl(),
'noindex' => false,

// UI
'fluid' => false,
'hideHeader' => false,
'heading' => null,

// Navegación
'breadcrumbs' => null, // [['label'=>'Inicio','url'=>route('home')], ...]
])

@php
$appName = config('app.name');
$pageTitle = $title ? "{$title} · {$appName}" : $appName;
$container = $fluid ? 'max-w-none w-full' : 'container mx-auto px-4';
$robots = $noindex ? 'noindex, nofollow' : 'index, follow';
$desc = $description ?: config('app.description');
$img = $image ? (Str::startsWith($image, ['http://','https://','//']) ? $image : (function_exists('asset') ?
asset($image) : $image)) : null;

// JSON-LD Breadcrumbs
$breadcrumbJson = null;
if (is_array($breadcrumbs) && !empty($breadcrumbs)) {
$items = [];
foreach (array_values($breadcrumbs) as $i => $bc) {
$items[] = [
'@type' => 'ListItem',
'position' => $i + 1,
'name' => (string) ($bc['label'] ?? ''),
'item' => (string) ($bc['url'] ?? url('/')),
];
}
$breadcrumbJson = [
'@context' => 'https://schema.org',
'@type' => 'BreadcrumbList',
'itemListElement' => $items,
];
}
@endphp

<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}"
      class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>

    <meta name="robots"
          content="{{ $robots }}">
    @if($desc)
    <meta name="description"
          content="{{ $desc }}">@endif
    @if($canonical)
    <link rel="canonical"
          href="{{ $canonical }}">@endif

    {{-- Open Graph --}}
    <meta property="og:type"
          content="website">
    <meta property="og:title"
          content="{{ $title ?: $appName }}">
    @if($desc)
    <meta property="og:description"
          content="{{ $desc }}">@endif
    <meta property="og:url"
          content="{{ $canonical }}">
    @if($img)
    <meta property="og:image"
          content="{{ $img }}">@endif

    {{-- Twitter Card --}}
    <meta name="twitter:card"
          content="{{ $img ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title"
          content="{{ $title ?: $appName }}">
    @if($desc)
    <meta name="twitter:description"
          content="{{ $desc }}">@endif
    @if($img)
    <meta name="twitter:image"
          content="{{ $img }}">@endif

    {{-- Color y esquema --}}
    <meta name="color-scheme"
          content="light dark">
    <meta name="theme-color"
          content="#0ea5e9">

    {{-- JSON-LD breadcrumbs --}}
    @if($breadcrumbJson)
    <script
            type="application/ld+json">{!! json_encode($breadcrumbJson, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    {{-- Placeholders para stacks del proyecto (si los usás) --}}
    @stack('head')
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body class="min-h-full bg-gray-50/40 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
    {{-- Navbar global (si tenés un componente, incluilo aquí) --}}
    @hasSection('nav')
    @yield('nav')
    @endif

    {{-- Header opcional --}}
    @unless($hideHeader)
    <header class="border-b border-gray-200/60 dark:border-gray-700/60 bg-white/80 dark:bg-gray-800/60 backdrop-blur">
        <div class="{{ $container }} py-6 flex items-center justify-between gap-4">
            <div>
                @if(is_array($breadcrumbs) && !empty($breadcrumbs))
                <nav aria-label="Breadcrumb"
                     class="mb-2">
                    <ol class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        @foreach($breadcrumbs as $i => $bc)
                        @if(!empty($bc['url']) && $i < count($breadcrumbs)-1)
                          <li><a href="{{ $bc['url'] }}"
                               class="hover:text-gray-900 dark:hover:text-white">{{ $bc['label'] }}</a></li>
                            <li aria-hidden="true">/</li>
                            @else
                            <li aria-current="page"
                                class="text-gray-900 dark:text-white font-medium">{{ $bc['label'] }}</li>
                            @endif
                            @endforeach
                    </ol>
                </nav>
                @endif

                @if($heading)
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">{{ $heading }}</h1>
                @if($desc)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $desc }}</p>
                @endif
                @endif

                {{-- Slot "header" para contenido libre bajo el h1 --}}
                @isset($header)
                <div class="mt-3">
                    {{ $header }}
                </div>
                @endisset
            </div>

            {{-- Acciones a la derecha del header (botones, filtros, etc.) --}}
            @isset($actions)
            <div class="shrink-0 flex items-center gap-2">
                {{ $actions }}
            </div>
            @endisset
        </div>
    </header>
    @endunless

    {{-- Contenido principal --}}
    <main id="content"
          tabindex="-1"
          class="{{ $container }} py-6">
        {{ $slot }}
    </main>

    {{-- Footer básico (opcional) --}}
    <footer
            class="mt-8 border-t border-gray-200/60 dark:border-gray-700/60 py-6 text-sm text-gray-500 dark:text-gray-400">
        <div class="{{ $container }} flex justify-between">
            <div>&copy; {{ date('Y') }} {{ $appName }}</div>
            <div class="space-x-4">
                {{-- enlaces del footer si aplica --}}
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>

</html>