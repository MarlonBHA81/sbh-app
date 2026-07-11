<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Profile;
use App\Models\Topic;
use App\Services\Feed\FeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function following(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        return PostResource::collection($feeds->following($this->activeProfile($request)));
    }

    public function forYou(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        return PostResource::collection($feeds->forYou($this->activeProfile($request)));
    }

    public function nearby(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'between:1,100'],
        ]);

        return PostResource::collection($feeds->nearby(
            $this->activeProfile($request),
            (float) $validated['lat'],
            (float) $validated['lng'],
            (float) ($validated['radius_km'] ?? 25),
        ));
    }

    public function topic(Request $request, string $slug, FeedService $feeds): AnonymousResourceCollection
    {
        $topic = Topic::query()->where('slug', $slug)->firstOrFail();

        return PostResource::collection($feeds->topic($this->activeProfile($request), $topic));
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
