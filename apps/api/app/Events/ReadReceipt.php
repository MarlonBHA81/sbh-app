<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Profile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A participant advanced their read cursor to a given message.
 */
class ReadReceipt implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public Profile $reader,
        public Message $message,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('conversation.'.$this->conversation->ulid)];
    }

    public function broadcastAs(): string
    {
        return 'ReadReceipt';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'profile_ulid' => $this->reader->ulid,
            'message_ulid' => $this->message->ulid,
        ];
    }
}
