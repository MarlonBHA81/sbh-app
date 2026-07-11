<?php

use App\Support\Geohash;
use Illuminate\Support\Facades\Http;

test('setting a profile location stores geohash, share flag and geocoded place', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Paris, France',
            'address' => ['city' => 'Paris', 'country' => 'France', 'country_code' => 'fr'],
        ], 200),
    ]);

    $me = userWithProfile();
    $profile = $me->personalProfile;

    $this->actingAs($me)->postJson("/api/v1/me/profiles/{$profile->ulid}/location", [
        'lat' => 48.8566,
        'lng' => 2.3522,
    ])->assertOk()
        ->assertJsonPath('data.share_location', true)
        ->assertJsonPath('data.country_code', 'FR')
        ->assertJsonPath('data.city', 'Paris');

    $profile->refresh();
    expect($profile->share_location)->toBeTrue();
    expect($profile->geohash)->toBe(Geohash::encode(48.8566, 2.3522, 9));
    expect((float) $profile->lat)->toBe(48.8566);
});

test('setting a location tolerates a null geocode result', function () {
    config()->set('services.nominatim.enabled', false);

    $me = userWithProfile();
    $profile = $me->personalProfile;

    $this->actingAs($me)->postJson("/api/v1/me/profiles/{$profile->ulid}/location", [
        'lat' => 10.0,
        'lng' => 20.0,
    ])->assertOk()
        ->assertJsonPath('data.share_location', true)
        ->assertJsonPath('data.city', null);

    $profile->refresh();
    expect($profile->share_location)->toBeTrue();
    expect($profile->geohash)->not->toBeNull();
});

test('a profile location cannot be set on someone elses profile', function () {
    $me = userWithProfile();
    $other = userWithProfile();

    $this->actingAs($me)->postJson("/api/v1/me/profiles/{$other->personalProfile->ulid}/location", [
        'lat' => 1.0,
        'lng' => 2.0,
    ])->assertForbidden();
});

test('clearing a location resets coordinates but keeps the country code', function () {
    config()->set('services.nominatim.enabled', false);

    $me = userWithProfile();
    $profile = $me->personalProfile;
    $profile->forceFill([
        'lat' => 48.8566,
        'lng' => 2.3522,
        'geohash' => Geohash::encode(48.8566, 2.3522, 9),
        'country_code' => 'FR',
        'city' => 'Paris',
        'share_location' => true,
    ])->save();

    $this->actingAs($me)->deleteJson("/api/v1/me/profiles/{$profile->ulid}/location")
        ->assertOk()
        ->assertJsonPath('data.share_location', false);

    $profile->refresh();
    expect($profile->lat)->toBeNull();
    expect($profile->geohash)->toBeNull();
    expect($profile->city)->toBeNull();
    expect($profile->share_location)->toBeFalse();
    expect($profile->country_code)->toBe('FR');
});

test('location endpoints require authentication', function () {
    $me = userWithProfile();

    $this->postJson("/api/v1/me/profiles/{$me->personalProfile->ulid}/location", [
        'lat' => 1.0, 'lng' => 2.0,
    ])->assertUnauthorized();
});
