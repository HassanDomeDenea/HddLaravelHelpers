<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\BroadcastsEvents;

trait HasHddBroadcastsEvents
{
    use BroadcastsEvents;

    public function broadcastWith(string $event): array
    {
        return [
            'event' => $event,
            'data' => $this->toData()->toArray(),
        ];
    }

    public function broadcastOn($event): array
    {
        return [
            $this,
            new PrivateChannel('App.Models'),
            new PrivateChannel('App.Models.SpecificSocket.'.request()->header('X-Socket-ID')),
        ];
    }
}
