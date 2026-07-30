<?php

use App\Models\Setting;

test('the me endpoint exposes the resolved feature flags', function () {
    $user = userWithProfile();

    Setting::set('features.shop', false);

    $res = $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    expect($res->json('features.shop'))->toBeFalse()
        ->and($res->json('features.daily_brief'))->toBeTrue()
        ->and($res->json('features'))->toHaveKeys(['shop', 'courses', 'masterclasses', 'gamification']);
});

test('a disabled feature flag 404s its routes; enabled serves them', function () {
    $user = userWithProfile();

    // Enabled by default → the route is reachable (200).
    $this->actingAs($user)->getJson('/api/v1/shop/stores')->assertOk();

    // Turn it off → the same route disappears (404).
    Setting::set('features.shop', false);
    $this->actingAs($user)->getJson('/api/v1/shop/stores')->assertNotFound();

    // Other flags are unaffected.
    $this->actingAs($user)->getJson('/api/v1/challenges')->assertOk();

    Setting::set('features.gamification', false);
    $this->actingAs($user)->getJson('/api/v1/challenges')->assertNotFound();
});
