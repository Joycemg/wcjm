<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\GameTableClosed;
use App\Listeners\RecordVoteHistory;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseEventServiceProvider;

final class EventServiceProvider extends BaseEventServiceProvider
{
    /**
     * Mapeo explícito de eventos -> listeners.
     * Útil en hosting compartido: evita el "event discovery" y reduce I/O.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        GameTableClosed::class => [
            RecordVoteHistory::class,
        ],
    ];

    /**
     * Para entornos con recursos limitados, es mejor mantenerlo en false.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
