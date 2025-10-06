<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class GuestLayout extends Component
{
    /**
     * Props pensadas para páginas de invitado (auth, reset, verificación, etc.).
     * Por defecto, marcamos noindex para que buscadores no indexen páginas sensibles.
     *
     * Ejemplo de uso:
     * <x-guest-layout title="Ingresar" description="Accedé a tu cuenta">
     *   ...form...
     * </x-guest-layout>
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $image = null,      // OG/Twitter image
        public ?string $bodyClass = null,  // clases para <body>
        public ?string $lang = null,
        public bool $noindex = true,       // ✅ default: no indexar páginas guest
        public bool $fluid = false,        // ancho completo
        public bool $hideHeader = true,    // header minimal u oculto
        public bool $hideFooter = true,    // footer oculto
    ) {
        // Normalizaciones ligeras
        $this->title = $this->clean($this->title);
        $this->description = $this->limit($this->clean($this->description), 200);
        $this->bodyClass = trim((string) $this->bodyClass);
        $this->lang = $this->lang ?: app()->getLocale();
    }

    /**
     * Renderiza la vista del layout con meta listo para imprimir en <head>.
     *
     * @return View|Closure|string
     */
    public function render(): View|Closure|string
    {
        $appName = (string) config('app.name', 'App');
        $pageTitle = $this->pageTitle($appName, $this->title);

        $image = $this->image ?: (string) config('app.og_image', 'images/og-default.png');
        $imageAbs = $this->absoluteAsset($image);

        $meta = [
            'title' => $pageTitle,
            'description' => $this->metaDescription($this->description, $appName),
            'canonical' => $this->canonicalUrl($this->canonical),
            'robots' => $this->noindex ? 'noindex, nofollow' : 'index, follow',
            'lang' => $this->lang,

            // Open Graph / Twitter
            'og:type' => 'website',
            'og:title' => $pageTitle,
            'og:description' => $this->metaDescription($this->description, $appName),
            'og:url' => url()->current(),
            'og:image' => $imageAbs,
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $pageTitle,
            'twitter:description' => $this->metaDescription($this->description, $appName),
            'twitter:image' => $imageAbs,
        ];

        return view('layouts.guest', [
            'pageTitle' => $pageTitle,
            'meta' => $meta,
            'bodyClass' => $this->bodyClass,
            'lang' => $this->lang,
            // flags de presentación
            'fluid' => $this->fluid,
            'hideHeader' => $this->hideHeader,
            'hideFooter' => $this->hideFooter,
        ]);
    }

    /* =========================
     * Helpers privados
     * ========================= */

    private function pageTitle(string $appName, ?string $title): string
    {
        // "Título – AppName" o solo AppName si no hay título
        return $title ? ($title . ' – ' . $appName) : $appName;
    }

    private function metaDescription(?string $desc, string $appName): string
    {
        $d = $this->limit($this->clean($desc ?: ''), 160);
        return $d !== '' ? $d : $appName;
    }

    private function canonicalUrl(?string $value): ?string
    {
        if (!$value || $value === '') {
            return url()->current();
        }
        if (Str::startsWith($value, ['http://', 'https://', '//'])) {
            return $value; // ya es absoluta
        }
        return url($value); // vuelve absoluta
    }

    private function absoluteAsset(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://', '//'])) {
            return $pathOrUrl;
        }
        $rel = asset(ltrim($pathOrUrl, '/'));
        return Str::startsWith($rel, ['http://', 'https://']) ? $rel : url($rel);
    }

    private function clean(?string $value): ?string
    {
        if ($value === null)
            return null;
        $t = strip_tags($value);
        $t = preg_replace('/\s+/', ' ', $t ?? '') ?? '';
        return trim($t);
    }

    private function limit(?string $value, int $max): ?string
    {
        if ($value === null)
            return null;
        return Str::limit($value, $max, '');
    }
}
