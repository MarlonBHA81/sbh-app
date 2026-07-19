<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CoachMessageResource;
use App\Models\Profile;
use App\Services\Coach\CoachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CoachController extends Controller
{
    public function __construct(private CoachService $coach) {}

    /** The member's coach conversation history. */
    public function show(Request $request): AnonymousResourceCollection
    {
        $profile = $this->activeProfile($request);

        return CoachMessageResource::collection($this->coach->history($profile));
    }

    /** Send a message to the coach and get a reply. */
    public function store(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $result = $this->coach->send($profile, $data['body']);

        return response()->json([
            'data' => [
                'user' => new CoachMessageResource($result['user']),
                'assistant' => new CoachMessageResource($result['assistant']),
            ],
        ], 201);
    }

    /** Clear the member's coach conversation. */
    public function destroy(Request $request): Response
    {
        $this->coach->reset($this->activeProfile($request));

        return response()->noContent();
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
