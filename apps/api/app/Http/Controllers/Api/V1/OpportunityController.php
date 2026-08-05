<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpportunityResource;
use App\Models\Opportunity;
use App\Models\Profile;
use App\Services\Opportunities\OpportunityFitService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    private const PER_PAGE = 20;

    /** Published, open opportunities — soonest-closing first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['sometimes', 'nullable', Rule::in(Opportunity::TYPES)],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'province' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $viewer = $this->activeProfile($request);

        $page = Opportunity::query()
            ->visible()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['industry'] ?? null, fn ($q, $industry) => $q->where('industry', $industry))
            ->when($filters['province'] ?? null, fn ($q, $province) => $q->where('province', $province))
            // Sponsored/partner opportunities lead the feed (honestly labelled).
            ->orderByDesc('is_sponsored')
            ->orderByRaw('closes_at is null asc')
            ->orderBy('closes_at')
            ->orderByDesc('published_at')
            ->cursorPaginate(self::PER_PAGE);

        return OpportunityResource::collection($this->markSaved($page, $viewer));
    }

    /**
     * Fit-ranked opportunities for the member (V3 · personalised Home) — ordered
     * by relevance to their industry, closing date and official/partner status.
     */
    public function suggested(Request $request, OpportunityFitService $fit): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        $opportunities = $fit->forProfile($viewer, 6);

        $ids = $opportunities->pluck('id');
        $savedIds = $ids->isEmpty()
            ? collect()
            : $viewer->savedOpportunities()->whereIn('opportunities.id', $ids)->pluck('opportunities.id');

        $opportunities->each(fn (Opportunity $o) => $o->setAttribute('is_saved', $savedIds->contains($o->id)));

        return OpportunityResource::collection($opportunities);
    }

    /** The viewer's saved opportunities, most recently saved first. */
    public function saved(Request $request): AnonymousResourceCollection
    {
        $viewer = $this->activeProfile($request);

        $page = $viewer->savedOpportunities()
            ->visible()
            ->orderByDesc('opportunity_saves.created_at')
            ->cursorPaginate(self::PER_PAGE);

        // Everything on this list is, by definition, saved.
        foreach ($page as $opportunity) {
            $opportunity->setAttribute('is_saved', true);
        }

        return OpportunityResource::collection($page);
    }

    public function show(Request $request, Opportunity $opportunity): JsonResponse
    {
        abort_unless($opportunity->is_published, 404);

        $viewer = $this->activeProfile($request);
        $opportunity->setAttribute(
            'is_saved',
            $viewer->savedOpportunities()->whereKey($opportunity->id)->exists(),
        );

        return response()->json(['data' => new OpportunityResource($opportunity)]);
    }

    public function save(Request $request, Opportunity $opportunity): JsonResponse
    {
        abort_unless($opportunity->is_published, 404);

        $this->activeProfile($request)
            ->savedOpportunities()
            ->syncWithoutDetaching([$opportunity->id]);

        return response()->json(['data' => ['saved' => true]], 201);
    }

    public function unsave(Request $request, Opportunity $opportunity): Response
    {
        $this->activeProfile($request)
            ->savedOpportunities()
            ->detach($opportunity->id);

        return response()->noContent();
    }

    /** Flag which of the paginated opportunities the viewer has saved. */
    private function markSaved(CursorPaginator $page, Profile $viewer): CursorPaginator
    {
        $ids = collect($page->items())->pluck('id');

        $savedIds = $ids->isEmpty()
            ? collect()
            : $viewer->savedOpportunities()->whereIn('opportunities.id', $ids)->pluck('opportunities.id');

        foreach ($page as $opportunity) {
            $opportunity->setAttribute('is_saved', $savedIds->contains($opportunity->id));
        }

        return $page;
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
