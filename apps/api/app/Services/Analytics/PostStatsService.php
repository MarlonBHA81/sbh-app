<?php

namespace App\Services\Analytics;

use App\Models\Post;
use App\Models\PostStatsDaily;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the per-post daily rollup that powers the creator analytics
 * dashboard. Every countable engagement bumps today's row (UTC calendar day)
 * for the affected post.
 *
 * Design notes:
 * - Buckets are counted over UTC calendar days, matching the gamification
 *   daily-cap convention.
 * - Removals are NOT decremented. Unliking, deleting a comment or removing a
 *   repost leaves the recorded engagement in place; the daily rollup is a
 *   historical activity log, not a live mirror of the post counters.
 */
class PostStatsService
{
    public const VIEWS = 'views';

    public const LIKES = 'likes';

    public const COMMENTS = 'comments';

    public const REPOSTS = 'reposts';

    public const VOTES = 'votes';

    private const METRICS = [
        self::VIEWS, self::LIKES, self::COMMENTS, self::REPOSTS, self::VOTES,
    ];

    /**
     * Upsert-increment today's rollup row for $post, adding $by to $metric.
     */
    public function bump(Post $post, string $metric, int $by = 1): void
    {
        if (! in_array($metric, self::METRICS, true) || $by === 0) {
            return;
        }

        $date = now()->utc()->toDateString();

        // Portable upsert-increment: insert a zeroed row for today if absent
        // (ignoring the unique collision when it already exists), then
        // increment the target column. Works on SQLite and MySQL.
        PostStatsDaily::query()->insertOrIgnore([
            'post_id' => $post->id,
            'date' => $date,
        ]);

        PostStatsDaily::query()
            ->where('post_id', $post->id)
            ->where('date', $date)
            ->update([$metric => DB::raw("{$metric} + ".(int) $by)]);
    }
}
