<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Public post channel event: a comment was added to a post.
 */
class CommentAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('post.'.$this->comment->post->ulid)];
    }

    public function broadcastAs(): string
    {
        return 'CommentAdded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $comment = $this->comment;
        $profile = $comment->profile;

        return [
            'ulid' => $comment->ulid,
            'post_ulid' => $comment->post->ulid,
            'parent_comment_ulid' => $comment->parent?->ulid,
            'depth' => $comment->depth,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toISOString(),
            'profile' => [
                'ulid' => $profile->ulid,
                'handle' => $profile->handle,
                'name' => $profile->name,
                'avatar_url' => $profile->avatarUrl(),
            ],
        ];
    }
}
