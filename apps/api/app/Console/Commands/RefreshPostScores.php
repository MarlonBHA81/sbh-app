<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\Feed\ForYouScorer;
use Illuminate\Console\Command;

class RefreshPostScores extends Command
{
    protected $signature = 'posts:refresh-scores';

    protected $description = 'Recompute for-you scores for recently published posts';

    public function handle(ForYouScorer $scorer): int
    {
        $count = 0;

        Post::query()
            ->published()
            ->where('published_at', '>=', now()->subHours(72))
            ->chunkById(200, function ($posts) use ($scorer, &$count) {
                foreach ($posts as $post) {
                    $post->forceFill(['score' => $scorer->score($post)])->saveQuietly();
                    $count++;
                }
            });

        $this->info("Refreshed scores for {$count} post(s).");

        return self::SUCCESS;
    }
}
