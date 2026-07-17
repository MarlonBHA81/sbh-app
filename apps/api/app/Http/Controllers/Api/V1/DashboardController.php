<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Business dashboard (V3 · PROGRESS) — a member's "see your growth" view.
 * Assembles the profile's real, self-owned signals (goals, streak, helpful
 * count, posts, XP) into one payload. Every number here is genuine; nothing is
 * inflated and a member is never shown a fabricated figure.
 */
class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $goals = Goal::query()
            ->forProfile($viewer)
            ->orderBy('is_done')
            ->orderByDesc('created_at')
            ->get();

        $snapshot = $viewer->streakSnapshot();

        return response()->json([
            'data' => [
                'stats' => [
                    'posts_count' => (int) $viewer->posts()->count(),
                    'helpful_count' => (int) $viewer->helpful_count,
                    'xp_total' => (int) $viewer->xp_total,
                    'streak_days' => (int) $snapshot['current_days'],
                    'goals_completed' => $goals->where('is_done', true)->count(),
                    'goals_total' => $goals->count(),
                ],
                'goals' => GoalResource::collection($goals),
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
