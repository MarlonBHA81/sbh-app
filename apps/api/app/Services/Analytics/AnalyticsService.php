<?php

namespace App\Services\Analytics;

use App\Models\Follow;
use App\Models\Post;
use App\Models\PostStatsDaily;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Read-side aggregation of the post_stats_daily rollup for the creator
 * dashboard. All windows are inclusive of the trailing $days UTC calendar days.
 */
class AnalyticsService
{
    private const ALLOWED_DAYS = [7, 30, 90];

    public function normaliseDays(mixed $days): int
    {
        $days = (int) $days;

        return in_array($days, self::ALLOWED_DAYS, true) ? $days : 30;
    }

    /**
     * Totals plus a zero-filled per-day series over the window.
     *
     * @return array{totals: array<string, int>, series: list<array<string, mixed>>}
     */
    public function overview(Profile $profile, int $days): array
    {
        $start = now()->utc()->startOfDay()->subDays($days - 1);
        $startDate = $start->toDateString();

        $postIds = Post::query()->where('profile_id', $profile->id)->pluck('id');

        $rows = PostStatsDaily::query()
            ->whereIn('post_id', $postIds)
            ->where('date', '>=', $startDate)
            ->selectRaw('date, '
                .'sum(views) as views, sum(likes) as likes, sum(comments) as comments, '
                .'sum(reposts) as reposts, sum(votes) as votes')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => (string) $row->date->toDateString());

        $series = [];
        $totals = ['views' => 0, 'likes' => 0, 'comments' => 0, 'reposts' => 0, 'votes' => 0];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $row = $rows->get($day);

            $point = [
                'date' => $day,
                'views' => (int) ($row->views ?? 0),
                'likes' => (int) ($row->likes ?? 0),
                'comments' => (int) ($row->comments ?? 0),
                'reposts' => (int) ($row->reposts ?? 0),
            ];

            foreach (['views', 'likes', 'comments', 'reposts', 'votes'] as $metric) {
                $totals[$metric] += (int) ($row->{$metric} ?? 0);
            }

            $series[] = $point;
        }

        $totals['followers_gained'] = Follow::query()
            ->where('followed_profile_id', $profile->id)
            ->where('state', Follow::STATE_ACCEPTED)
            ->where('created_at', '>=', $start)
            ->count();

        $totals['posts_published'] = Post::query()
            ->where('profile_id', $profile->id)
            ->published()
            ->where('published_at', '>=', $start)
            ->count();

        return ['totals' => $totals, 'series' => $series];
    }

    /**
     * The profile's own posts ranked by their view count over the window, with
     * per-post engagement metrics. Cursor-paginated by rank then id.
     */
    public function posts(Profile $profile, int $days): CursorPaginator
    {
        $startDate = now()->utc()->startOfDay()->subDays($days - 1)->toDateString();

        $stats = PostStatsDaily::query()
            ->where('date', '>=', $startDate)
            ->selectRaw('post_id, '
                .'sum(views) as period_views, sum(likes) as period_likes, '
                .'sum(comments) as period_comments, sum(reposts) as period_reposts')
            ->groupBy('post_id');

        return Post::query()
            ->where('posts.profile_id', $profile->id)
            ->published()
            ->leftJoinSub($stats, 'stats', 'stats.post_id', '=', 'posts.id')
            ->select('posts.*')
            ->selectRaw('coalesce(stats.period_views, 0) as period_views')
            ->selectRaw('coalesce(stats.period_likes, 0) as period_likes')
            ->selectRaw('coalesce(stats.period_comments, 0) as period_comments')
            ->selectRaw('coalesce(stats.period_reposts, 0) as period_reposts')
            ->orderByRaw('coalesce(stats.period_views, 0) desc')
            ->orderByDesc('posts.id')
            ->cursorPaginate(20);
    }

    /**
     * Engagement rate for a ranked-post row hydrated by posts().
     */
    public function engagementRatePct(int $views, int $likes, int $comments, int $reposts): float
    {
        return round(($likes + $comments + $reposts) / max($views, 1) * 100, 2);
    }
}
