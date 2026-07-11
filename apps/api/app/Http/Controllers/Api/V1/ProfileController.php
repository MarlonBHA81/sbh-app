<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Follow;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileController extends Controller
{
    public function show(Request $request, string $handle): ProfileResource
    {
        $profile = $this->resolveByHandle($handle);
        $profile->load('badges');

        return new ProfileResource($profile);
    }

    public function followers(Request $request, string $handle): AnonymousResourceCollection
    {
        $profile = $this->resolveByHandle($handle);

        $this->authorizeListAccess($request, $profile);

        $followers = $profile->followers()
            ->wherePivot('state', Follow::STATE_ACCEPTED)
            ->orderByPivot('id')
            ->cursorPaginate(20);

        return ProfileResource::collection($followers);
    }

    public function following(Request $request, string $handle): AnonymousResourceCollection
    {
        $profile = $this->resolveByHandle($handle);

        $this->authorizeListAccess($request, $profile);

        $following = $profile->following()
            ->wherePivot('state', Follow::STATE_ACCEPTED)
            ->orderByPivot('id')
            ->cursorPaginate(20);

        return ProfileResource::collection($following);
    }

    private function resolveByHandle(string $handle): Profile
    {
        return Profile::query()
            ->where('handle', mb_strtolower($handle))
            ->firstOrFail();
    }

    private function authorizeListAccess(Request $request, Profile $profile): void
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        abort_unless($profile->isViewableBy($viewer), 403, 'This profile is private.');
    }
}
