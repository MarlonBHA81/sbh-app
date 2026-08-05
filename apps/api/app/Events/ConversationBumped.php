<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use App\Support\MessagePayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight fan-out to each OTHER participant's private profile channel so
 * their conversation list re-sorts and the nav unread badge updates without
 * subscribing to the conversation presence channel.
 *
 * @property list<string> $recipientProfileUlids
 */
class ConversationBumped implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $recipientProfileUlids
     */
    public function __construct(
        public Conversation $conversation,
        public Message $message,
        public array $recipientProfileUlids,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (string $ulid) => new PrivateChannel('profile.'.$ulid),
            $this->recipientProfileUlids,
        );
    }

    public function broadcastAs(): string
    {
        return 'ConversationBumped';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_ulid' => $this->conversation->ulid,
            'last_message' => MessagePayload::liteMessage($this->message),
            'sender_profile_ulid' => $this->message->profile->ulid,
        ];
    }
}
