<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WellnessCheckinResource;
use App\Http\Resources\WellnessResourceResource;
use App\Models\Profile;
use App\Models\WellnessCheckin;
use App\Models\WellnessResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Wellness & resilience space (V3 · BELONG). A calm corner: admin-curated
 * supportive resources anyone can read, plus a member's own private check-ins.
 * No gamification, no ranking, no XP — supportive by design.
 */
class WellnessController extends Controller
{
    /** Published wellness prompts/reads, in the admin's chosen order. */
    public function resources(): AnonymousResourceCollection
    {
        $resources = WellnessResource::query()
            ->published()
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        return WellnessResourceResource::collection($resources);
    }

    /** The viewer's own recent check-ins — private to them. */
    public function checkins(Request $request): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        $checkins = WellnessCheckin::query()
            ->where('profile_id', $viewer->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return WellnessCheckinResource::collection($checkins);
    }

    /** Log a private check-in. Never rewarded or shared. */
    public function storeCheckin(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $data = $request->validate([
            'mood' => ['required', 'integer', 'between:'.WellnessCheckin::MIN_MOOD.','.WellnessCheckin::MAX_MOOD],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $checkin = WellnessCheckin::create([
            'profile_id' => $viewer->id,
            'mood' => $data['mood'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['data' => new WellnessCheckinResource($checkin)], 201);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
