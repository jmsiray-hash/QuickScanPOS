<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SharedDataChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $resource,
        public readonly ?string $cartToken = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('pos-sync')];

        if ($this->cartToken !== null) {
            $channels[] = new Channel('cart.'.$this->cartToken);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'pos.sync';
    }

    public function broadcastWith(): array
    {
        return ['resource' => $this->resource];
    }
}
