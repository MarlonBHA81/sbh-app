<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Profile;
use App\Services\Business\BusinessEventService;
use App\Support\ViewerReactions;
use App\Support\ViewerSatelliteState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BusinessEventController extends Controller
{
    public function index(Request $request, BusinessEventService $events): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'filter' => ['sometimes', 'in:upcoming,past'],
            'lat' => ['sometimes', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['sometimes', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['sometimes', 'numeric', 'between:1,500'],
        ]);

        $viewer = $this->activeProfile($request);

        $hasGeo = isset($validated['lat'], $validated['lng']);

        $paginator = $events->events(
            $viewer,
            $validated['filter'] ?? 'upcoming',
            $hasGeo ? (float) $validated['lat'] : null,
            $hasGeo ? (float) $validated['lng'] : null,
            $hasGeo ? (float) ($validated['radius_km'] ?? 25) : null,
        );

        ViewerReactions::hydrate($paginator->getCollection(), $viewer);
        ViewerSatelliteState::hydrate($paginator->getCollection(), $viewer);

        return PostResource::collection($paginator);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
