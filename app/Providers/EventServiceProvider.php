<?php

namespace App\Providers;

use App\Events\GameTableClosed;
use App\Listeners\RecordVoteHistory;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseEventServiceProvider;

final class EventServiceProvider extends BaseEventServiceProvider
{
    protected $listen = [
        GameTableClosed::class => [
            RecordVoteHistory::class,
        ],
    ];
}
