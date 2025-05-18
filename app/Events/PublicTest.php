<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicTest implements ShouldBroadcastNow
{
    public function __construct(public string $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('public-chat');
    }

    public function broadcastAs(): string
    {
        return 'public-message';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
