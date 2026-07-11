<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Feed\ForYouScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecomputePostScore implements ShouldQueue
{
    use Queueable;

    public function __construct(public Post $post) {}

    public function handle(ForYouScorer $scorer): void
    {
        $post = $this->post->fresh();

        if (! $post || ! $post->isPublished()) {
            return;
        }

        $post->forceFill(['score' => $scorer->score($post)])->saveQuietly();
    }
}
