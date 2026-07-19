<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\Profile;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    /**
     * Published lessons, ordered by track then position. Optionally filtered by
     * journey stage. Each lesson is flagged with the viewer's completion state.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'journey_stage' => ['sometimes', 'nullable', Rule::in(Profile::JOURNEY_STAGES)],
        ]);

        $viewer = $this->activeProfile($request);

        $lessons = Lesson::query()
            ->visible()
            ->with('track')
            ->when($filters['journey_stage'] ?? null, fn ($q, $stage) => $q->where('journey_stage', $stage))
            ->orderByRaw('lesson_track_id is null asc')
            ->orderBy('lesson_track_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $this->markCompleted($lessons, $viewer);

        return LessonResource::collection($lessons);
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->is_published, 404);

        $viewer = $this->activeProfile($request);
        $lesson->load('track');
        $this->markCompleted(collect([$lesson]), $viewer);

        // Next published lesson in the same track (by position), if any.
        $next = $lesson->lesson_track_id === null
            ? null
            : Lesson::query()
                ->visible()
                ->where('lesson_track_id', $lesson->lesson_track_id)
                ->where(fn ($q) => $q->where('position', '>', $lesson->position)
                    ->orWhere(fn ($q2) => $q2->where('position', $lesson->position)->where('id', '>', $lesson->id)))
                ->orderBy('position')
                ->orderBy('id')
                ->first();

        return response()->json([
            'data' => new LessonResource($lesson),
            'next' => $next ? ['ulid' => $next->ulid, 'title' => $next->title] : null,
        ]);
    }

    /**
     * Mark a lesson complete for the viewer. Idempotent: re-completing never
     * double-awards XP (guarded by the lesson subject on the ledger).
     */
    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->is_published, 404);

        $viewer = $this->activeProfile($request);

        $viewer->completedLessons()->syncWithoutDetaching([
            $lesson->id => ['completed_at' => now()],
        ]);

        $this->gamification->award($viewer, GamificationService::LESSON_COMPLETED, $lesson);

        return response()->json(['data' => [
            'is_completed' => true,
            'progress' => $this->progressFor($viewer),
        ]]);
    }

    /** The viewer's learning progress: completed vs total published lessons. */
    public function progress(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        return response()->json(['data' => $this->progressFor($viewer)]);
    }

    /**
     * @return array{completed:int, total:int}
     */
    private function progressFor(Profile $viewer): array
    {
        $total = Lesson::query()->visible()->count();

        $completed = $viewer->completedLessons()
            ->where('is_published', true)
            ->count();

        return ['completed' => $completed, 'total' => $total];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Lesson>  $lessons
     */
    private function markCompleted($lessons, Profile $viewer): void
    {
        $ids = $lessons->pluck('id');

        $completedIds = $ids->isEmpty()
            ? collect()
            : $viewer->completedLessons()->whereIn('lessons.id', $ids)->pluck('lessons.id');

        foreach ($lessons as $lesson) {
            $lesson->setAttribute('is_completed', $completedIds->contains($lesson->id));
        }
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
