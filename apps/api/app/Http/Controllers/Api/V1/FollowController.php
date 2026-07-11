<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FollowController extends Controller
{
    public function store(Request $request, string $handle, ProfileService $profiles): JsonResponse
    {
        $target = $this->resolveByHandle($handle);
        $actor = $this->activeProfile($request);

        $follow = $profiles->follow($actor, $target);

        return response()->json([
            'state' => $follow->state,
            'relationship' => $target->relationshipStateFor($actor),
        ], 201);
    }

    public function destroy(Request $request, string $handle, ProfileService $profiles): Response
    {
        $target = $this->resolveByHandle($handle);
        $actor = $this->activeProfile($request);

        $profiles->unfollow($actor, $target);

        return response()->noContent();
    }

    private function resolveByHandle(string $handle): Profile
    {
        return Profile::query()
            ->where('handle', mb_strtolower($handle))
            ->firstOrFail();
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
