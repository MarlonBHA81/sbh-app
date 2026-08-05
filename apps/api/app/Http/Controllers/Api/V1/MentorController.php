<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\Connections\MentorSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorController extends Controller
{
    /** Mentors matched to the member, most relevant first (V2 · CONNECT). */
    public function index(Request $request, MentorSuggestionService $mentors): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $data = array_map(fn (array $row) => [
            'profile' => (new ProfileResource($row['profile']))->toArray($request),
            'reason' => $row['reason'],
        ], $mentors->suggest($viewer, 20));

        return response()->json(['data' => $data]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
