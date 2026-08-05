<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An ephemeral "Zoom-style" live reaction in a room (ask #4). Broadcast to
 * everyone on the room's conversation presence channel and animated on screen;
 * never persisted, so it leaves no chat history.
 */
class LiveReaction implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationUlid,
        public string $emoji,
        public ?string $handle = null,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('conversation.'.$this->conversationUlid)];
    }

    public function broadcastAs(): string
    {
        return 'LiveReaction';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['emoji' => $this->emoji, 'handle' => $this->handle];
    }
}
