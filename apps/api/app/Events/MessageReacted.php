<?php

namespace App\Events;

use App\Models\Message;
use App\Support\MessagePayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message's reaction set changed. Carries the full grouped summary so
 * clients can replace their local state wholesale.
 */
class MessageReacted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('conversation.'.$this->message->conversation->ulid)];
    }

    public function broadcastAs(): string
    {
        return 'MessageReacted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_ulid' => $this->message->ulid,
            'reactions' => MessagePayload::reactions($this->message, null),
        ];
    }
}
