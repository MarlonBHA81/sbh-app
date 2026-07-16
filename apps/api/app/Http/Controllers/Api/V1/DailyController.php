<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyAction;
use App\Models\Profile;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    /** Today's challenge, whether the viewer has done it, and their streak. */
    public function today(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);
        $action = DailyAction::forDate();

        return response()->json([
            'data' => [
                'action' => $action ? $this->serializeAction($action) : null,
                'completed' => $this->completedToday($viewer),
                'streak' => $viewer->streakSnapshot(),
            ],
        ]);
    }

    /** Mark today's challenge done — awards XP and advances the streak once. */
    public function complete(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);
        $action = DailyAction::forDate();

        abort_if($action === null, 404, 'No challenge today.');

        $already = $this->completedToday($viewer);

        if (! $already) {
            // Unique (profile, day) row guarantees one completion per day even
            // under a double-tap race.
            DB::table('daily_action_completions')->insertOrIgnore([
                'daily_action_id' => $action->id,
                'profile_id' => $viewer->id,
                'completed_on' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $viewer->advanceStreak();
            $this->gamification->award($viewer, GamificationService::DAILY_ACTION_COMPLETED, $action);
        }

        return response()->json([
            'data' => [
                'completed' => true,
                'streak' => $viewer->fresh()->streakSnapshot(),
            ],
        ]);
    }

    /** The viewer's engagement streak (powers the streak chip). */
    public function streak(Request $request): JsonResponse
    {
        $snapshot = $this->activeProfile($request)->streakSnapshot();

        // Null when there's nothing worth showing, matching the client stub.
        return response()->json([
            'data' => $snapshot['current_days'] > 0 ? $snapshot : null,
        ]);
    }

    private function completedToday(Profile $viewer): bool
    {
        return DB::table('daily_action_completions')
            ->where('profile_id', $viewer->id)
            ->where('completed_on', now()->toDateString())
            ->exists();
    }

    private function serializeAction(DailyAction $action): array
    {
        return [
            'ulid' => $action->ulid,
            'title' => $action->title,
            'description' => $action->description,
        ];
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
