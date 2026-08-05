<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Posts\PostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishScheduledPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Post $post) {}

    public function handle(PostService $posts): void
    {
        $post = $this->post->fresh();

        if (! $post || $post->status !== Post::STATUS_SCHEDULED) {
            return;
        }

        $posts->publish($post);
    }
}
