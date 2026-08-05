<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Feeds the frontend's dynamic sitemap.xml: recent public profiles and
 * posts. Cached for an hour — crawlers don't need fresher than that.
 */
class PublicSitemapController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('public:sitemap', self::CACHE_TTL_SECONDS, function () {
            $profiles = Profile::query()
                ->where('is_private', false)
                ->whereHas('user', fn ($q) => $q->whereNull('banned_at'))
                ->latest('updated_at')
                ->limit(500)
                ->get(['handle', 'updated_at'])
                ->map(fn (Profile $profile) => [
                    'handle' => $profile->handle,
                    'updated_at' => $profile->updated_at?->toISOString(),
                ]);

            $posts = Post::query()
                ->where('status', Post::STATUS_PUBLISHED)
                ->where('visibility', Post::VISIBILITY_PUBLIC)
                ->whereHas('profile', fn ($q) => $q->where('is_private', false))
                ->latest('published_at')
                ->limit(1000)
                ->get(['ulid', 'published_at', 'updated_at'])
                ->map(fn (Post $post) => [
                    'ulid' => $post->ulid,
                    'updated_at' => ($post->updated_at ?? $post->published_at)?->toISOString(),
                ]);

            return ['profiles' => $profiles, 'posts' => $posts];
        });

        return response()->json(['data' => $payload]);
    }
}
