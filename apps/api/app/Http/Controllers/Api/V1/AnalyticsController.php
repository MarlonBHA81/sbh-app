<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $days = $analytics->normaliseDays($request->query('days'));

        return response()->json(['days' => $days] + $analytics->overview($profile, $days));
    }

    public function posts(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $days = $analytics->normaliseDays($request->query('days'));

        $paginator = $analytics->posts($profile, $days);

        $data = collect($paginator->items())->map(function (Post $post) use ($analytics) {
            $views = (int) $post->getAttribute('period_views');
            $likes = (int) $post->getAttribute('period_likes');
            $comments = (int) $post->getAttribute('period_comments');
            $reposts = (int) $post->getAttribute('period_reposts');

            return [
                'ulid' => $post->ulid,
                'type' => $post->type,
                'body' => $post->body,
                'published_at' => $post->published_at?->toISOString(),
                'views' => $views,
                'likes' => $likes,
                'comments' => $comments,
                'reposts' => $reposts,
                'engagement_rate_pct' => $analytics->engagementRatePct($views, $likes, $comments, $reposts),
            ];
        })->all();

        return response()->json([
            'days' => $days,
            'data' => $data,
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
