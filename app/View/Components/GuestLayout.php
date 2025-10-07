<?php

declare(strict_types=1);

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\View\Component;

final class GuestLayout extends Component
{
    /**
     * Props pensadas para páginas de invitado (auth, reset, verificación, etc.).
     * Por defecto: noindex para que buscadores no indexen páginas sensibles.
     *
     * <x-guest-layout title="Ingresar" description="Accedé a tu cuenta">
     *   ...form...
     * </x-guest-layout>
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $image = null,      // OG/Twitter image (ruta o URL)
        public ?string $bodyClass = null,  // clases extra en <body>
        public ?string $lang = null,       // ej: "es-AR"
        public bool $noindex = true,       // ✅ default: no indexar
        public bool $fluid = false,        // ancho completo
        public bool $hideHeader = true,    // header minimal u oculto
        public bool $hideFooter = true     // footer oculto
    ) {
        // Normalizaciones ligeras y seguras
        $this->title = $this->clean($this->title);
        $this->description = $this->limit($this->clean($this->description), 200);
        $this->bodyClass = trim((string) $this->bodyClass);
        $this->lang = $this->normalizeLang($this->lang ?: app()->getLocale());
    }

    /**
     * Renderiza la vista del layout con meta listo para el <head>.
     */
    public function render(): View|Closure|string
    {
        $appName = (string) config('app.name', 'App');
        $pageTitle = $this->buildPageTitle($appName, $this->title);

        // Descripción final (fallback: nombre de la app)
        $desc = $this->metaDescription($this->description, $appName);

        // Canonical absoluto y normalizado al host de APP_URL
        $canonical = $this->normalizedCanonical($this->canonical);

        // Imagen OG/Twitter absoluta
        $img = $this->image ?: (string) config('app.og_image', 'images/og-default.png');
        $imgAbs = $this->absoluteAsset($img);

        // Robots según prop (por defecto sensible)
        $robots = $this->noindex ? 'noindex, nofollow' : 'index, follow';

        // Metadatos listos para iterar en la vista
        $meta = [
            // Básicos
            'title' => $pageTitle,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $robots,
            'lang' => $this->lang,
            'color-scheme' => 'light',

            // Open Graph
            'og:type' => 'website',
            'og:title' => $pageTitle,
            'og:description' => $desc,
            'og:url' => $canonical ?? URL::current(),
            'og:image' => $imgAbs,

            // Twitter
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $pageTitle,
            'twitter:description' => $desc,
            'twitter:image' => $imgAbs,

            // Informativo
            'generator' => 'Laravel',
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

    private function buildPageTitle(string $appName, ?string $title): string
    {
        // "Título – AppName" o solo AppName si no hay título
        return $title ? ($title . ' – ' . $appName) : $appName;
    }

    private function metaDescription(?string $desc, string $appName): string
    {
        $d = $this->limit($this->clean($desc ?? ''), 160);
        return $d !== '' ? $d : $appName;
    }

    /**
     * Devuelve una URL canónica absoluta y normalizada al host de APP_URL.
     * Si no se especifica, usa la URL "current" sin querystring.
     */
    private function normalizedCanonical(?string $value): ?string
    {
        $raw = $value && $value !== '' ? $value : URL::current();

        // Absolutizar si vino relativa
        if (!Str::startsWith($raw, ['http://', 'https://', '//'])) {
            $raw = URL::to($raw);
        }

        // Normalizar host/esquema al de APP_URL para evitar contenidos duplicados
        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '') {
            $appParts = parse_url($appUrl);
            $rawParts = parse_url($raw);

            if (is_array($appParts) && is_array($rawParts)) {
                $scheme = $appParts['scheme'] ?? ($rawParts['scheme'] ?? 'https');
                $host = $appParts['host'] ?? ($rawParts['host'] ?? null);
                $port = $appParts['port'] ?? null;
                $path = $rawParts['path'] ?? '';
                $query = null; // canonical sin query por defecto

                $built = $scheme . '://' . $host
                    . ($port ? (':' . $port) : '')
                    . $path
                    . ($query ? ('?' . $query) : '');

                return $built;
            }
        }

        return $raw;
    }

    /**
     * Convierte una ruta (storage/public) o ruta relativa a una URL absoluta.
     */
    private function absoluteAsset(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://', '//'])) {
            return $pathOrUrl;
        }
        $rel = asset(ltrim($pathOrUrl, '/'));
        return Str::startsWith($rel, ['http://', 'https://']) ? $rel : URL::to($rel);
    }

    /**
     * Limpieza básica de texto (quita HTML, espacios repetidos).
     */
    private function clean(?string $value): ?string
    {
        if ($value === null)
            return null;
        $t = strip_tags($value);
        $t = preg_replace('/\s+/', ' ', $t ?? '') ?? '';
        return trim($t);
    }

    /**
     * Limita la longitud de forma segura.
     */
    private function limit(?string $value, int $max): ?string
    {
        if ($value === null)
            return null;
        return Str::limit($value, $max, '');
    }

    /**
     * Normaliza etiqueta de idioma a IETF BCP 47-like:
     * - Reemplaza "_" por "-"
     * - Lowercase para idioma, UPPERCASE para región (es-AR, en-US, pt-BR)
     */
    private function normalizeLang(?string $tag): string
    {
        $tag = trim((string) $tag);
        if ($tag === '') {
            return app()->getLocale();
        }

        $tag = str_replace('_', '-', $tag);
        // Partes: es, es-AR, zh-Hant-TW, etc.
        $parts = explode('-', $tag);
        foreach ($parts as $i => $p) {
            if ($i === 0) {
                $parts[$i] = strtolower($p); // idioma
            } elseif (strlen($p) === 2) {
                $parts[$i] = strtoupper($p); // región 2 letras
            } else {
                // script/variants: Capitalize primera
                $parts[$i] = ucfirst(strtolower($p));
            }
        }

        $norm = implode('-', $parts);

        // Validación simple (evita cosas raras)
        if (!preg_match('/^[A-Za-z]{2,8}(-[A-Za-z0-9]{2,8})*$/', $norm)) {
            return app()->getLocale();
        }

        return $norm;
    }
}
