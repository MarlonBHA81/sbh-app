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
