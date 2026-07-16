<?php

use App\Models\AdEvent;
use App\Models\Opportunity;

function makeOpp(array $attributes = []): Opportunity
{
    return Opportunity::create(array_merge([
        'type' => 'funding',
        'title' => 'A funding opportunity',
        'description' => 'Some description.',
        'is_published' => true,
    ], $attributes));
}

test('sponsored opportunities lead the feed and are labelled', function () {
    $user = userWithProfile();

    makeOpp(['title' => 'Organic soonest', 'closes_at' => now()->addDay()]);
    makeOpp([
        'title' => 'Sponsored later',
        'closes_at' => now()->addMonths(2),
        'is_sponsored' => true,
        'sponsor_name' => 'Acme Bank',
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/opportunities')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Sponsored later')
        ->assertJsonPath('data.0.is_sponsored', true)
        ->assertJsonPath('data.0.sponsor_name', 'Acme Bank')
        ->assertJsonPath('data.1.title', 'Organic soonest');
});

test('a sponsored impression and click are recorded in ad_events', function () {
    $user = userWithProfile();
    $opp = makeOpp(['is_sponsored' => true, 'sponsor_name' => 'Acme']);

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', ['kind' => 'impression', 'opportunity_ulid' => $opp->ulid])
        ->assertNoContent();

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', ['kind' => 'click', 'opportunity_ulid' => $opp->ulid])
        ->assertNoContent();

    expect(AdEvent::where('opportunity_id', $opp->id)->where('kind', 'impression')->count())->toBe(1);
    expect(AdEvent::where('opportunity_id', $opp->id)->where('kind', 'click')->count())->toBe(1);
});

test('a non-sponsored opportunity records no ad events', function () {
    $user = userWithProfile();
    $opp = makeOpp(['is_sponsored' => false]);

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', ['kind' => 'impression', 'opportunity_ulid' => $opp->ulid])
        ->assertNoContent();

    expect(AdEvent::where('opportunity_id', $opp->id)->count())->toBe(0);
});

test('sponsored impressions are deduped within the window', function () {
    $user = userWithProfile();
    $opp = makeOpp(['is_sponsored' => true]);

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($user)
            ->postJson('/api/v1/ads/track', ['kind' => 'impression', 'opportunity_ulid' => $opp->ulid])
            ->assertNoContent();
    }

    expect(AdEvent::where('opportunity_id', $opp->id)->where('kind', 'impression')->count())->toBe(1);
});

test('an opportunity track target cannot be combined with a campaign or slot', function () {
    $user = userWithProfile();
    $opp = makeOpp(['is_sponsored' => true]);

    $this->actingAs($user)
        ->postJson('/api/v1/ads/track', [
            'kind' => 'impression',
            'opportunity_ulid' => $opp->ulid,
            'slot_key' => 'right_rail_default',
        ])
        ->assertStatus(422);
});
