<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BriefItemResource;
use App\Models\Profile;
use App\Services\Brief\BriefService;
use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BriefController extends Controller
{
    public function __construct(private BriefService $brief) {}

    /** The member's personalised Daily Business Brief. */
    public function show(Request $request): JsonResponse
    {
        // Super admins can switch the whole surface off. The web card self-hides
        // on an empty payload, so return one without touching the AI.
        if (! Features::enabled('daily_brief')) {
            return response()->json([
                'data' => ['headline' => '', 'date' => now()->toDateString(), 'items' => []],
            ]);
        }

        $profile = $this->activeProfile($request);

        $brief = $this->brief->forProfile($profile);

        return response()->json([
            'data' => [
                'headline' => $brief['headline'],
                'date' => $brief['date'],
                'items' => BriefItemResource::collection($brief['items']),
            ],
        ]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
