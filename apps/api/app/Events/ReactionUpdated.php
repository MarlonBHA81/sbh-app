<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Public post channel event: a post's like/vote counters changed.
 */
class ReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Post $post) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('post.'.$this->post->ulid)];
    }

    public function broadcastAs(): string
    {
        return 'ReactionUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'post_ulid' => $this->post->ulid,
            'likes_count' => (int) $this->post->likes_count,
            'upvotes_count' => (int) $this->post->upvotes_count,
            'downvotes_count' => (int) $this->post->downvotes_count,
        ];
    }
}
