<?php

use App\Models\AdEvent;
use App\Models\AdSlot;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\User;

function campaignFor(User $user, array $attributes = []): Campaign
{
    $post = Post::factory()->create(['profile_id' => $user->personalProfile->id]);

    return Campaign::factory()->create(array_merge([
        'profile_id' => $user->personalProfile->id,
        'post_id' => $post->id,
    ], $attributes));
}

test('an impression increments counters, spends one CPI and records an event', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => 10000, 'cpi_cents' => 2]);

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression',
        'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    $campaign->refresh();

    expect($campaign->impressions_count)->toBe(1)
        ->and($campaign->spent_cents)->toBe(2)
        ->and(AdEvent::query()->where('campaign_id', $campaign->id)->where('kind', 'impression')->count())->toBe(1);
});

test('a repeat impression from the same profile within the dedupe window is not counted', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => 10000, 'cpi_cents' => 2]);

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression', 'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression', 'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    $campaign->refresh();

    expect($campaign->impressions_count)->toBe(1)
        ->and($campaign->spent_cents)->toBe(2);
});

test('distinct viewers each count as an impression', function () {
    $advertiser = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => 10000, 'cpi_cents' => 2]);

    foreach ([userWithProfile(), userWithProfile()] as $viewer) {
        $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
            'kind' => 'impression', 'campaign_ulid' => $campaign->ulid,
        ])->assertNoContent();
    }

    expect($campaign->refresh()->impressions_count)->toBe(2)
        ->and($campaign->spent_cents)->toBe(4);
});

test('an impression completes the campaign when the budget is exhausted', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => 2, 'cpi_cents' => 2]);

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression', 'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    $campaign->refresh();

    expect($campaign->spent_cents)->toBe(2)
        ->and($campaign->status)->toBe(Campaign::STATUS_COMPLETED);
});

test('clicks increment the click counter, do not spend and are not deduped', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => 10000, 'cpi_cents' => 2]);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
            'kind' => 'click', 'campaign_ulid' => $campaign->ulid,
        ])->assertNoContent();
    }

    $campaign->refresh();

    expect($campaign->clicks_count)->toBe(2)
        ->and($campaign->spent_cents)->toBe(0);
});

test('tracking a paused campaign is a no-op', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();
    $campaign = campaignFor($advertiser, ['status' => Campaign::STATUS_PAUSED]);

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression', 'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    expect($campaign->refresh()->impressions_count)->toBe(0)
        ->and(AdEvent::query()->count())->toBe(0);
});

test('a slot impression records an event only', function () {
    $viewer = userWithProfile();
    $slot = AdSlot::factory()->create();

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression', 'slot_key' => $slot->key,
    ])->assertNoContent();

    expect(AdEvent::query()->where('ad_slot_id', $slot->id)->where('kind', 'impression')->count())->toBe(1);
});

test('the ads:settle command completes expired active campaigns', function () {
    $advertiser = userWithProfile();
    $expired = campaignFor($advertiser, ['starts_at' => now()->subDays(5), 'ends_at' => now()->subDay()]);
    $live = campaignFor($advertiser, ['starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);

    $this->artisan('ads:settle')->assertSuccessful();

    expect($expired->refresh()->status)->toBe(Campaign::STATUS_COMPLETED)
        ->and($live->refresh()->status)->toBe(Campaign::STATUS_ACTIVE);
});

test('a non-admin can still track ad impressions', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();

    expect($viewer->is_admin)->toBeFalse();

    $campaign = campaignFor($advertiser, ['budget_cents' => 10000, 'cpi_cents' => 2]);

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression',
        'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    expect($campaign->refresh()->impressions_count)->toBe(1);
});

test('an impression on an unbudgeted campaign counts but never spends', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => null, 'cpi_cents' => 2]);

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'impression',
        'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    $campaign->refresh();

    expect($campaign->impressions_count)->toBe(1)
        ->and($campaign->spent_cents)->toBe(0)
        ->and($campaign->status)->toBe(Campaign::STATUS_ACTIVE);
});

test('a link click increments its own counter and records an event', function () {
    $advertiser = userWithProfile();
    $viewer = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => null]);

    $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
        'kind' => 'link_click',
        'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    $campaign->refresh();

    expect($campaign->link_clicks_count)->toBe(1)
        ->and($campaign->clicks_count)->toBe(0)
        ->and(AdEvent::query()->where('campaign_id', $campaign->id)
            ->where('kind', AdEvent::KIND_LINK_CLICK)->count())->toBe(1);
});

test('the campaign detail includes link clicks, reach and daily series', function () {
    $advertiser = adminWithProfile();
    $viewerA = userWithProfile();
    $viewerB = userWithProfile();
    $campaign = campaignFor($advertiser, ['budget_cents' => null]);

    foreach ([$viewerA, $viewerB] as $viewer) {
        $this->actingAs($viewer)->postJson('/api/v1/ads/track', [
            'kind' => 'impression',
            'campaign_ulid' => $campaign->ulid,
        ])->assertNoContent();
    }

    $this->actingAs($viewerA)->postJson('/api/v1/ads/track', [
        'kind' => 'link_click',
        'campaign_ulid' => $campaign->ulid,
    ])->assertNoContent();

    $response = $this->actingAs($advertiser)
        ->getJson("/api/v1/ads/campaigns/{$campaign->ulid}?series=1")
        ->assertOk()
        ->assertJsonPath('data.impressions', 2)
        ->assertJsonPath('data.link_clicks', 1)
        ->assertJsonPath('data.reach', 2);

    $today = collect($response->json('data.series'))->firstWhere('date', now()->toDateString());

    expect($today)->not->toBeNull()
        ->and($today['impressions'])->toBe(2)
        ->and($today['link_clicks'])->toBe(1);
});
