<?php

namespace App\View\Components;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout principal para páginas internas (loggeadas o públicas con chrome completo).
 *
 * Props comunes:
 * - title?: string                    -> Título de la página (se concatena con el nombre de la app)
 * - description?: string              -> Meta description (limpiada/limitada)
 * - image?: string                    -> OG/Twitter image (ruta o URL absoluta)
 * - canonical?: string                -> URL canónica (se absolutiza si es relativa)
 * - noindex?: bool                    -> Robots noindex (default false en app)
 * - fluid?: bool                      -> Contenido a ancho completo
 * - hideHeader?: bool                 -> Ocultar header (por ejemplo en páginas limpias)
 * - heading?: string                  -> Título visible en el contenido (fallback al title)
 * - breadcrumbs?: array<string|array{label:string,url?:string|null}>
 */
class AppLayout extends Component
{
    /**
     * Normalized breadcrumb items.
     *
     * @var array<int, array{label: string, url: string|null}>
     */
    public array $breadcrumbs;

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $canonical = null,
        public bool $noindex = false,
        public bool $fluid = false,
        public bool $hideHeader = false,
        public ?string $heading = null,
        array $breadcrumbs = []
    ) {
        // Normalizaciones ligeras y seguras
        $this->title = $this->clean($this->title);
        $this->description = $this->limit($this->clean($this->description), 200);
        $this->heading = $this->clean($this->heading);
        $this->breadcrumbs = $this->normalizeBreadcrumbs($breadcrumbs);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $appName = (string) config('app.name', 'App');

        // Título completo: "Title · App" o "App" si no hay title
        $pageTitle = $this->title ? ($this->title . ' · ' . $appName) : $appName;

        // Meta base
        $desc = $this->metaDescription($this->description, $appName);
        $image = $this->absoluteAsset($this->image ?: (string) config('app.og_image', 'images/og-default.png'));
        $canonical = $this->canonicalUrl($this->canonical);

        $meta = [
            'title' => $pageTitle,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $this->noindex ? 'noindex, nofollow' : 'index, follow',

            // Open Graph / Twitter
            'og:type' => 'website',
            'og:title' => $pageTitle,
            'og:description' => $desc,
            'og:url' => url()->current(),
            'og:image' => $image,
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $pageTitle,
            'twitter:description' => $desc,
            'twitter:image' => $image,
        ];

        // Heading visible (fallback al title simple, no al "Title · App")
        $heading = $this->heading ?: ($this->title ?: $appName);

        return view('components.app-layout', [
            // SEO / HEAD
            'pageTitle' => $pageTitle,
            'meta' => $meta,

            // UI flags
            'fluid' => $this->fluid,
            'hideHeader' => $this->hideHeader,

            // Contenido
            'heading' => $heading,
            'breadcrumbs' => $this->breadcrumbs,
        ]);
    }

    /* =========================
     * Helpers públicos/privados
     * ========================= */

    /**
     * Prepare the breadcrumb collection ensuring each item has a label/url.
     *
     * @param  array<mixed>  $items
     * @return array<int, array{label: string, url: string|null}>
     */
    protected function normalizeBreadcrumbs(array $items): array
    {
        return collect($items)
            ->map(function ($item) {
                // Permitir string directo como label
                if (is_string($item)) {
                    $label = trim($item);
                    return $label !== ''
                        ? ['label' => $label, 'url' => null]
                        : null;
                }

                // Permitir arrays con label/url
                if (is_array($item)) {
                    $label = trim((string) Arr::get($item, 'label', ''));
                    if ($label === '') {
                        return null;
                    }

                    $url = Arr::get($item, 'url');
                    $url = is_string($url) && $url !== '' ? $url : null;

                    // Absolutizar si viene relativa (para evitar duplicados raros en OG)
                    if (is_string($url)) {
                        $url = $this->absoluteUrl($url);
                    }

                    return ['label' => $label, 'url' => $url];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Meta description con fallback al nombre de la app.
     */
    protected function metaDescription(?string $desc, string $appName): string
    {
        $d = $this->limit($this->clean($desc ?: ''), 160);
        return $d !== '' ? $d : $appName;
    }

    /**
     * Devuelve URL canónica absoluta.
     */
    protected function canonicalUrl(?string $value): string
    {
        if (!$value || $value === '') {
            return url()->current();
        }
        if (Str::startsWith($value, ['http://', 'https://', '//'])) {
            return $value;
        }
        return url($value);
    }

    /**
     * Absolutiza una URL (si es relativa) usando url().
     */
    protected function absoluteUrl(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://', '//'])) {
            return $url;
        }
        return url($url);
    }

    /**
     * Absolutiza una ruta de asset (si es relativa) usando asset() + url().
     */
    protected function absoluteAsset(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://', '//'])) {
            return $pathOrUrl;
        }
        $rel = asset(ltrim($pathOrUrl, '/'));
        return Str::startsWith($rel, ['http://', 'https://']) ? $rel : url($rel);
    }

    /**
     * Limpia HTML/espacios. Devuelve null si input es null.
     */
    protected function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = strip_tags($value);
        $t = preg_replace('/\s+/', ' ', $t ?? '') ?? '';
        return trim($t);
    }

    /**
     * Limita longitud de texto (sin sufijo) conservando nulls.
     */
    protected function limit(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        return Str::limit($value, $max, '');
    }
}
