<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Masterclass;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Masterclasses & cohorts (V3 · LEARN) — member-facing list + enrolment.
 * Programmes are curated by admins in Filament; members enrol into the cohort.
 */
class MasterclassController extends Controller
{
    /** Published, not-yet-finished masterclasses — soonest first. */
    public function index(Request $request): JsonResponse
    {
        $viewer = $this->activeProfile($request);

        $classes = Masterclass::query()
            ->visible()
            ->withCount('participants')
            ->orderByDesc('is_sponsored')
            ->orderBy('starts_at')
            ->limit(30)
            ->get();

        $enrolledIds = $viewer->masterclasses()->pluck('masterclasses.id')->all();

        return response()->json([
            'data' => $classes->map(
                fn (Masterclass $m) => $this->serialize($m, in_array($m->id, $enrolledIds, true)),
            ),
        ]);
    }

    public function show(Request $request, Masterclass $masterclass): JsonResponse
    {
        abort_unless($masterclass->is_published, 404);

        $viewer = $this->activeProfile($request);
        $masterclass->loadCount('participants');

        $enrolled = $masterclass->participants()->where('profile_id', $viewer->id)->exists();

        return response()->json(['data' => $this->serialize($masterclass, $enrolled)]);
    }

    /** Enrol the member into the cohort. */
    public function enrol(Request $request, Masterclass $masterclass): JsonResponse
    {
        abort_unless($masterclass->is_published, 404);
        abort_if($masterclass->hasEnded(), 422, 'This masterclass has already finished.');

        $viewer = $this->activeProfile($request);

        $already = $masterclass->participants()->where('profile_id', $viewer->id)->exists();

        if (! $already) {
            abort_if($masterclass->isFull(), 422, 'This masterclass is full.');

            $masterclass->participants()->attach($viewer->id, ['enrolled_at' => now()]);
        }

        $masterclass->loadCount('participants');

        return response()->json(['data' => $this->serialize($masterclass, true)], 201);
    }

    /** Withdraw from the cohort. */
    public function withdraw(Request $request, Masterclass $masterclass): Response
    {
        $viewer = $this->activeProfile($request);

        $masterclass->participants()->detach($viewer->id);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Masterclass $masterclass, bool $enrolled): array
    {
        return [
            'ulid' => $masterclass->ulid,
            'title' => $masterclass->title,
            'description' => $masterclass->description,
            'facilitator_name' => $masterclass->facilitator_name,
            'brand_color' => $masterclass->brand_color,
            'accent_color' => $masterclass->accent_color,
            'logo_url' => $masterclass->logoUrl(),
            'banner_url' => $masterclass->bannerUrl(),
            'is_sponsored' => $masterclass->is_sponsored,
            'sponsor_name' => $masterclass->sponsor_name,
            'sponsor_url' => $masterclass->sponsor_url,
            'sponsor_blurb' => $masterclass->sponsor_blurb,
            'starts_at' => $masterclass->starts_at->toIso8601String(),
            'ends_at' => $masterclass->ends_at->toIso8601String(),
            'status' => $masterclass->hasEnded()
                ? 'ended'
                : ($masterclass->starts_at->isFuture() ? 'upcoming' : 'active'),
            'capacity' => $masterclass->capacity,
            'seats_left' => $masterclass->seatsLeft(),
            'participants_count' => (int) ($masterclass->participants_count ?? 0),
            'enrolled' => $enrolled,
        ];
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
