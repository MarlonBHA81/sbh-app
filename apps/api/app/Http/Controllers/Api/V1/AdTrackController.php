<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackAdRequest;
use App\Models\AdSlot;
use App\Models\Campaign;
use App\Models\Profile;
use App\Services\Ads\AdTrackingService;
use Illuminate\Http\Response;

class AdTrackController extends Controller
{
    public function store(TrackAdRequest $request, AdTrackingService $tracking): Response
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        $kind = $request->validated('kind');

        if ($ulid = $request->validated('campaign_ulid')) {
            $campaign = Campaign::query()->where('ulid', $ulid)->first();

            if ($campaign !== null) {
                $tracking->trackCampaign($campaign, $kind, $viewer);
            }
        } elseif ($key = $request->validated('slot_key')) {
            $slot = AdSlot::query()->where('key', $key)->where('active', true)->first();

            if ($slot !== null) {
                $tracking->trackSlot($slot, $kind, $viewer);
            }
        }

        return response()->noContent();
    }
}
