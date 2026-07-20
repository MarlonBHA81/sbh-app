<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CoachMessageResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\OpportunityResource;
use App\Models\Lesson;
use App\Models\Profile;
use App\Services\Coach\CoachService;
use App\Services\Opportunities\OpportunityFitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CoachController extends Controller
{
    public function __construct(
        private CoachService $coach,
        private OpportunityFitService $fit,
    ) {}

    /**
     * Deep Coach (V3): fit-ranked opportunities + recommended lessons for the
     * member, so the coach surface can point them at concrete next steps.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);

        $opportunities = $this->fit->forProfile($profile, 5);

        $lessons = Lesson::query()->visible()
            ->when($profile->journey_stage, fn ($q, $stage) => $q->where('journey_stage', $stage))
            ->limit(3)->get();

        if ($lessons->isEmpty()) {
            $lessons = Lesson::query()->visible()->limit(3)->get();
        }

        return response()->json([
            'data' => [
                'opportunities' => OpportunityResource::collection($opportunities),
                'lessons' => LessonResource::collection($lessons),
            ],
        ]);
    }

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
