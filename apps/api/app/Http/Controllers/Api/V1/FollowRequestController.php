<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FollowRequestResource;
use App\Models\Follow;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FollowRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $active = $this->activeProfile($request);

        $requests = Follow::query()
            ->where('followed_profile_id', $active->id)
            ->where('state', Follow::STATE_PENDING)
            ->with('follower')
            ->orderBy('id')
            ->cursorPaginate(20);

        return FollowRequestResource::collection($requests);
    }

    public function accept(Request $request, Follow $follow, ProfileService $profiles): FollowRequestResource
    {
        $this->authorizeRequest($request, $follow);

        return new FollowRequestResource($profiles->acceptFollowRequest($follow));
    }

    public function decline(Request $request, Follow $follow, ProfileService $profiles): Response
    {
        $this->authorizeRequest($request, $follow);

        $profiles->declineFollowRequest($follow);

        return response()->noContent();
    }

    private function authorizeRequest(Request $request, Follow $follow): void
    {
        abort_unless(
            $follow->followed()->where('user_id', $request->user()->id)->exists(),
            403
        );

        abort_unless($follow->isPending(), 404);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
