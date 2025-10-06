<?php

namespace App\View\Components;

use Illuminate\Support\Arr;
use Illuminate\View\Component;
use Illuminate\View\View;

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
        $this->breadcrumbs = $this->normalizeBreadcrumbs($breadcrumbs);
    }

    /**
     * Prepare the breadcrumb collection ensuring each item has a label/url.
     */
    protected function normalizeBreadcrumbs(array $items): array
    {
        return collect($items)
            ->map(function ($item) {
                if (is_string($item)) {
                    return [
                        'label' => $item,
                        'url' => null,
                    ];
                }

                if (is_array($item)) {
                    $label = trim((string) Arr::get($item, 'label', ''));
                    if ($label === '') {
                        return null;
                    }

                    $url = Arr::get($item, 'url');
                    $url = is_string($url) && $url !== '' ? $url : null;

                    return [
                        'label' => $label,
                        'url' => $url,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.app-layout');
    }
}
