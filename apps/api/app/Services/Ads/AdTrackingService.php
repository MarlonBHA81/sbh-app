<?php

namespace App\Services\Ads;

use App\Models\AdEvent;
use App\Models\AdSlot;
use App\Models\Campaign;
use App\Models\Profile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Records ad impressions and clicks for promoted-post campaigns and static
 * sponsor slots, and settles campaign spend.
 *
 * Writes are cheap (a couple of indexed inserts/updates) so tracking runs
 * inline on the request rather than through a queue — this keeps the spend
 * accounting immediately consistent and trivially testable.
 */
class AdTrackingService
{
    /** Minimum gap between counted impressions for the same profile+campaign. */
    private const IMPRESSION_DEDUPE_MINUTES = 5;

    public function __construct(private CampaignService $campaigns) {}

    /**
     * Record an impression, post-open click or outbound link click against a
     * campaign. Impressions are deduped per profile per campaign within a
     * short window; clicks are never deduped. On budgeted campaigns an
     * impression bills one CPI and exhaustion completes the campaign;
     * unbudgeted campaigns just count. No-op for non-active campaigns.
     */
    public function trackCampaign(Campaign $campaign, string $kind, ?Profile $viewer): void
    {
        if ($campaign->status !== Campaign::STATUS_ACTIVE) {
            return;
        }

        if ($kind === AdEvent::KIND_IMPRESSION && $viewer !== null) {
            $key = "ad-imp:{$viewer->ulid}:{$campaign->ulid}";

            if (Cache::has($key)) {
                return;
            }

            Cache::put($key, true, now()->addMinutes(self::IMPRESSION_DEDUPE_MINUTES));
        }

        DB::transaction(function () use ($campaign, $kind, $viewer) {
            AdEvent::create([
                'campaign_id' => $campaign->id,
                'kind' => $kind,
                'profile_id' => $viewer?->id,
            ]);

            if ($kind === AdEvent::KIND_IMPRESSION) {
                $update = ['impressions_count' => DB::raw('impressions_count + 1')];

                // Unbudgeted (metrics-only) campaigns never accrue spend.
                if ($campaign->budget_cents !== null) {
                    $update['spent_cents'] = DB::raw('spent_cents + '.(int) $campaign->cpi_cents);
                }

                Campaign::query()->whereKey($campaign->id)->update($update);
            } elseif ($kind === AdEvent::KIND_LINK_CLICK) {
                Campaign::query()->whereKey($campaign->id)->update([
                    'link_clicks_count' => DB::raw('link_clicks_count + 1'),
                ]);
            } else {
                Campaign::query()->whereKey($campaign->id)->update([
                    'clicks_count' => DB::raw('clicks_count + 1'),
                ]);
            }
        });

        $this->campaigns->settle($campaign->refresh());
    }

    /**
     * Record an impression or click against a static sponsor slot. Slots have
     * no budget, so this only writes an ad_events row.
     */
    public function trackSlot(AdSlot $slot, string $kind, ?Profile $viewer): void
    {
        AdEvent::create([
            'ad_slot_id' => $slot->id,
            'kind' => $kind,
            'profile_id' => $viewer?->id,
        ]);
    }

    /**
     * Pick one active slot for the placement, weighted by its integer weight.
     * Returns null when no active slot exists for the placement.
     */
    public function pickSlot(string $placement): ?AdSlot
    {
        $slots = AdSlot::query()
            ->where('placement', $placement)
            ->where('active', true)
            ->get();

        if ($slots->isEmpty()) {
            return null;
        }

        $total = (int) $slots->sum(fn (AdSlot $slot) => max(1, $slot->weight));

        $roll = random_int(1, $total);

        foreach ($slots as $slot) {
            $roll -= max(1, $slot->weight);

            if ($roll <= 0) {
                return $slot;
            }
        }

        return $slots->last();
    }
}
