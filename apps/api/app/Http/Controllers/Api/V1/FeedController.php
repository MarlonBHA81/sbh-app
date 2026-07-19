<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Profile;
use App\Models\Topic;
use App\Services\Feed\FeedService;
use App\Support\ViewerReactions;
use App\Support\ViewerSatelliteState;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function following(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        return $this->present($feeds->following($viewer), $viewer);
    }

    public function forYou(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        return $this->present($feeds->forYou($viewer), $viewer);
    }

    public function questions(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'answered' => ['sometimes', 'boolean'],
        ]);

        $viewer = $this->activeProfile($request);

        $answered = array_key_exists('answered', $validated)
            ? (bool) $validated['answered']
            : null;

        return $this->present($feeds->questions($viewer, $answered), $viewer);
    }

    public function wins(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        return $this->present($feeds->wins($viewer), $viewer);
    }

    public function nearby(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'between:1,100'],
        ]);

        $viewer = $this->activeProfile($request);

        return $this->present($feeds->nearby(
            $viewer,
            (float) $validated['lat'],
            (float) $validated['lng'],
            (float) ($validated['radius_km'] ?? 25),
        ), $viewer);
    }

    public function local(Request $request, FeedService $feeds): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'scope' => ['sometimes', 'in:city,country'],
        ]);

        $scope = $validated['scope'] ?? 'country';

        $viewer = $this->activeProfile($request);

        if ($scope === 'city') {
            abort_if($viewer->city === null || $viewer->country_code === null, 422, __('Set your location first'));
        } else {
            abort_if($viewer->country_code === null, 422, __('Set your location first'));
        }

        return $this->present($feeds->local($viewer, $scope), $viewer);
    }

    public function topic(Request $request, string $slug, FeedService $feeds): AnonymousResourceCollection
    {
        $topic = Topic::query()->where('slug', $slug)->firstOrFail();

        $viewer = $this->activeProfile($request);

        return $this->present($feeds->topic($viewer, $topic), $viewer);
    }

    private function present(CursorPaginator $posts, ?Profile $viewer): AnonymousResourceCollection
    {
        ViewerReactions::hydrate($posts->getCollection(), $viewer);
        ViewerSatelliteState::hydrate($posts->getCollection(), $viewer);

        return PostResource::collection($posts);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
