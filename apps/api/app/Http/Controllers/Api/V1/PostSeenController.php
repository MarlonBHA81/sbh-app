<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Analytics\PostStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Records that the viewer has seen a batch of posts. Each visible post counts
 * as at most one view per profile per UTC day: a per-day cache set dedupes
 * repeat impressions from an infinite-scroll client re-reporting the same rows.
 */
class PostSeenController extends Controller
{
    private const TTL_HOURS = 48;

    public function store(Request $request, PostStatsService $stats): Response
    {
        $validated = $request->validate([
            'ulids' => ['required', 'array', 'max:20'],
            'ulids.*' => ['string'],
        ]);

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        abort_unless($viewer instanceof Profile, 422, 'No active profile.');

        $date = now()->utc()->toDateString();
        $key = "seen-day:{$viewer->ulid}:{$date}";
        $seen = Cache::get($key, []);

        $candidates = array_values(array_unique(array_diff($validated['ulids'], $seen)));

        if ($candidates === []) {
            return response()->noContent();
        }

        $posts = Post::query()
            ->published()
            ->whereIn('ulid', $candidates)
            ->get();

        $recorded = [];

        foreach ($posts as $post) {
            if (! $post->isVisibleTo($viewer)) {
                continue;
            }

            $post->increment('views_count');
            $stats->bump($post, PostStatsService::VIEWS);
            $recorded[] = $post->ulid;
        }

        if ($recorded !== []) {
            Cache::put(
                $key,
                array_values(array_unique([...$seen, ...$recorded])),
                now()->addHours(self::TTL_HOURS),
            );
        }

        return response()->noContent();
    }
}
