<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Models\Profile;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Member goals (V3 · PROGRESS). Each goal is scoped to the active profile;
 * a member only ever sees and edits their own list.
 */
class GoalController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    /** The active profile's goals — open first, then completed, newest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        $goals = Goal::query()
            ->forProfile($viewer)
            ->orderBy('is_done')
            ->orderByDesc('created_at')
            ->get();

        return GoalResource::collection($goals);
    }

    public function store(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target' => ['sometimes', 'nullable', 'string', 'max:255'],
            'due_on' => ['sometimes', 'nullable', 'date'],
        ]);

        $goal = Goal::create([
            'profile_id' => $viewer->id,
            'title' => $data['title'],
            'target' => $data['target'] ?? null,
            'due_on' => $data['due_on'] ?? null,
        ]);

        return response()->json(['data' => new GoalResource($goal)], 201);
    }

    public function update(Request $request, Goal $goal): JsonResponse
    {
        $this->authorizeGoal($request, $goal);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'target' => ['sometimes', 'nullable', 'string', 'max:255'],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        $wasDone = $goal->is_done;

        $goal->fill(collect($data)->except('is_done')->all());

        if (array_key_exists('is_done', $data)) {
            $goal->is_done = $data['is_done'];
            $goal->completed_at = $data['is_done'] ? ($goal->completed_at ?? now()) : null;
        }

        $goal->save();

        // Award XP the first time a goal is completed. Subject idempotency in the
        // gamification service guarantees re-completing the same goal is a no-op.
        if (! $wasDone && $goal->is_done) {
            $this->gamification->award(
                $this->activeProfile($request),
                GamificationService::GOAL_COMPLETED,
                $goal,
            );
        }

        return response()->json(['data' => new GoalResource($goal->fresh())]);
    }

    public function destroy(Request $request, Goal $goal): Response
    {
        $this->authorizeGoal($request, $goal);

        $goal->delete();

        return response()->noContent();
    }

    private function authorizeGoal(Request $request, Goal $goal): void
    {
        abort_unless($goal->profile_id === $this->activeProfile($request)->id, 404);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
