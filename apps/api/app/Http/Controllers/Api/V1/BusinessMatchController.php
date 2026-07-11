<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\Business\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessMatchController extends Controller
{
    public function index(Request $request, MatchmakingService $matchmaking): JsonResponse
    {
        $viewer = $this->businessProfile($request);

        $matches = $matchmaking->matches($viewer);

        $data = array_map(fn (array $match) => [
            'profile' => (new ProfileResource($match['profile']))->toArray($request),
            'score' => $match['score'],
            'matches' => $match['matches'],
        ], $matches);

        return response()->json(['data' => $data]);
    }

    private function businessProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness(), 403, 'Only business profiles can be matched.');

        return $profile;
    }
}
