<?php

namespace App\Console\Commands;

use App\Jobs\PublishScheduledPost;
use App\Models\Post;
use Illuminate\Console\Command;

class PublishDuePosts extends Command
{
    protected $signature = 'posts:publish-due';

    protected $description = 'Publish scheduled posts whose scheduled time has passed';

    public function handle(): int
    {
        $count = 0;

        Post::query()
            ->where('status', Post::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (Post $post) use (&$count) {
                PublishScheduledPost::dispatch($post);
                $count++;
            });

        $this->info("Dispatched {$count} due post(s) for publishing.");

        return self::SUCCESS;
    }
}
