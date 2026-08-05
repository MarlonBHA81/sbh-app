<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\Connections\ConnectionSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    /** Suggested people to meet today (V1 · CONNECT). */
    public function today(Request $request, ConnectionSuggestionService $suggestions): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $data = array_map(fn (array $row) => [
            'profile' => (new ProfileResource($row['profile']))->toArray($request),
            'reason' => $row['reason'],
        ], $suggestions->suggest($viewer, 6));

        return response()->json(['data' => $data]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
