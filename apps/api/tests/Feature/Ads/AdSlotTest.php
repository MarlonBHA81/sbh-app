<?php

use App\Models\AdSlot;

test('the slot endpoint returns an active slot for the placement', function () {
    $user = userWithProfile();
    $slot = AdSlot::factory()->placement(AdSlot::PLACEMENT_RIGHT_RAIL)->create([
        'sponsor_name' => 'Acme Co',
    ]);

    $this->actingAs($user)->getJson('/api/v1/ads/slots/right_rail')
        ->assertOk()
        ->assertJsonPath('data.key', $slot->key)
        ->assertJsonPath('data.sponsor_name', 'Acme Co')
        ->assertJsonStructure(['data' => ['key', 'name', 'sponsor_name', 'sponsor_url', 'image_url', 'body']]);
});

test('the slot endpoint returns 204 when no active slot exists', function () {
    $user = userWithProfile();
    AdSlot::factory()->placement(AdSlot::PLACEMENT_RIGHT_RAIL)->inactive()->create();
    AdSlot::factory()->placement(AdSlot::PLACEMENT_FEED_INLINE)->create();

    $this->actingAs($user)->getJson('/api/v1/ads/slots/right_rail')->assertNoContent();
});

test('the slot endpoint only ever returns active slots for the placement', function () {
    $user = userWithProfile();

    $active = AdSlot::factory()->placement(AdSlot::PLACEMENT_RIGHT_RAIL)->weight(5)->create();
    AdSlot::factory()->placement(AdSlot::PLACEMENT_RIGHT_RAIL)->inactive()->create();
    AdSlot::factory()->placement(AdSlot::PLACEMENT_FEED_INLINE)->create();

    $keys = collect(range(1, 20))->map(
        fn () => $this->actingAs($user)->getJson('/api/v1/ads/slots/right_rail')->json('data.key')
    )->unique();

    expect($keys->all())->toBe([$active->key]);
});

test('weighted selection can surface either active slot over many draws', function () {
    $user = userWithProfile();

    $a = AdSlot::factory()->placement(AdSlot::PLACEMENT_RIGHT_RAIL)->weight(10)->create();
    $b = AdSlot::factory()->placement(AdSlot::PLACEMENT_RIGHT_RAIL)->weight(10)->create();

    $keys = collect(range(1, 40))->map(
        fn () => $this->actingAs($user)->getJson('/api/v1/ads/slots/right_rail')->json('data.key')
    )->unique()->values();

    // Both active slots are eligible; over 40 balanced draws both should appear.
    expect($keys->sort()->values()->all())->toBe(collect([$a->key, $b->key])->sort()->values()->all());
});

test('an unknown placement is rejected', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/ads/slots/banner')->assertNotFound();
});
