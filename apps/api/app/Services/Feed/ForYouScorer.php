<?php

namespace App\Services\Feed;

use App\Models\Post;

/**
 * Hot-style ranking: engagement over time decay. Recomputed on publish
 * and refreshed periodically by posts:refresh-scores.
 */
class ForYouScorer
{
    private const GRAVITY = 1.6;

    public function score(Post $post): float
    {
        if ($post->published_at === null) {
            return 0.0;
        }

        $engagement = $post->likes_count
            + 2 * $post->comments_count
            + 3 * $post->reposts_count
            + 1.5 * ($post->upvotes_count - $post->downvotes_count)
            + 0.1 * log($post->views_count + 1);

        $hours = max(0, now()->getTimestamp() - $post->published_at->getTimestamp()) / 3600;

        return $engagement / pow($hours + 2, self::GRAVITY);
    }
}
