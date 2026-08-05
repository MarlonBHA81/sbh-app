<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\AdEvent;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Profile;
use App\Services\Ads\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $this->activeProfile($request);

        $campaigns = Campaign::query()
            ->where('profile_id', $profile->id)
            ->with('post')
            ->latest('id')
            ->cursorPaginate(20);

        return CampaignResource::collection($campaigns);
    }

    public function store(StoreCampaignRequest $request, CampaignService $campaigns): JsonResponse
    {
        $profile = $this->activeProfile($request);

        $post = Post::query()->where('ulid', $request->validated('post_ulid'))->first();

        abort_unless($post !== null, 404, 'Post not found.');

        $campaign = $campaigns->create($profile, $post, $request->validated());

        return (new CampaignResource($campaign->load('post')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Campaign $campaign): CampaignResource
    {
        $this->authorizeOwner($request, $campaign);

        $campaign->load('post');

        $resource = new CampaignResource($campaign);

        if ($request->boolean('series')) {
            $resource->withSeries($this->series($campaign));
            $resource->withReach($this->reach($campaign));
        }

        return $resource;
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign, CampaignService $campaigns): CampaignResource
    {
        $this->authorizeOwner($request, $campaign);

        $campaigns->setStatus($campaign, $request->validated('status'));

        return new CampaignResource($campaign->load('post'));
    }

    public function destroy(Request $request, Campaign $campaign, CampaignService $campaigns): CampaignResource
    {
        $this->authorizeOwner($request, $campaign);

        $campaigns->end($campaign);

        return new CampaignResource($campaign->load('post'));
    }

    /**
     * Unique profiles that recorded an impression (logged-in reach).
     */
    private function reach(Campaign $campaign): int
    {
        return (int) AdEvent::query()
            ->where('campaign_id', $campaign->id)
            ->where('kind', AdEvent::KIND_IMPRESSION)
            ->whereNotNull('profile_id')
            ->distinct()
            ->count('profile_id');
    }

    /**
     * Per-day impression/click counts across the campaign window, zero-filled.
     *
     * @return list<array{date: string, impressions: int, clicks: int, link_clicks: int}>
     */
    private function series(Campaign $campaign): array
    {
        $rows = AdEvent::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('date(created_at) as day, kind, count(*) as total')
            ->groupBy('day', 'kind')
            ->get()
            ->groupBy('day');

        $start = ($campaign->starts_at ?? $campaign->created_at ?? now())->copy()->startOfDay();
        $end = ($campaign->ends_at ?? now())->copy()->startOfDay();

        $today = now()->startOfDay();
        if ($end->greaterThan($today)) {
            $end = $today;
        }

        $series = [];

        for ($day = $start->copy(); $day->lessThanOrEqualTo($end); $day->addDay()) {
            $key = $day->toDateString();
            $dayRows = $rows->get($key, collect());

            $series[] = [
                'date' => $key,
                'impressions' => (int) ($dayRows->firstWhere('kind', AdEvent::KIND_IMPRESSION)->total ?? 0),
                'clicks' => (int) ($dayRows->firstWhere('kind', AdEvent::KIND_CLICK)->total ?? 0),
                'link_clicks' => (int) ($dayRows->firstWhere('kind', AdEvent::KIND_LINK_CLICK)->total ?? 0),
            ];
        }

        return $series;
    }

    private function authorizeOwner(Request $request, Campaign $campaign): void
    {
        $profile = $this->activeProfile($request);

        abort_unless($campaign->profile_id === $profile->id, 404);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
