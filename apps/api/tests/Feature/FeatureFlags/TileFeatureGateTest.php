<?php

use App\Models\Setting;
use App\Support\Features;

/*
|--------------------------------------------------------------------------
| Home tile feature gates
|--------------------------------------------------------------------------
|
| Each of the four "always-on" Home tiles is now gated by a super-admin
| feature flag. When the flag is off the matching member API route 404s
| (the surface disappears); when on it serves its normal response.
|
*/

test('the registry contains the four tile flags', function () {
    expect(Features::registry())->toHaveKeys(['community', 'ads', 'directory', 'business_tools']);
});

test('the community flag gates the mentors route', function () {
    $user = userWithProfile();

    // Enabled by default → reachable (not 404).
    $this->actingAs($user)->getJson('/api/v1/mentors')->assertOk();

    Setting::set('features.community', false);
    $this->actingAs($user)->getJson('/api/v1/mentors')->assertNotFound();
});

test('the ads flag gates the sponsor slot route', function () {
    $user = userWithProfile();

    // Enabled by default → the slot endpoint is reachable (204 when no slot is
    // available in the test DB, i.e. not a 404).
    $res = $this->actingAs($user)->getJson('/api/v1/ads/slots/right_rail');
    expect($res->status())->not->toBe(404);

    Setting::set('features.ads', false);
    $this->actingAs($user)->getJson('/api/v1/ads/slots/right_rail')->assertNotFound();
});

test('the directory flag gates the business directory route', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/business/directory')->assertOk();

    Setting::set('features.directory', false);
    $this->actingAs($user)->getJson('/api/v1/business/directory')->assertNotFound();
});

test('the business_tools flag gates the insights analytics route', function () {
    $user = userWithProfile();

    $this->actingAs($user)->getJson('/api/v1/analytics/overview')->assertOk();

    Setting::set('features.business_tools', false);
    $this->actingAs($user)->getJson('/api/v1/analytics/overview')->assertNotFound();
});
