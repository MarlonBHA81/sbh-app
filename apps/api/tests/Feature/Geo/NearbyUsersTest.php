<?php

use App\Models\Profile;
use App\Services\SafetyService;
use App\Support\Geohash;

const NU_LAT = 0.0;
const NU_LNG = 10.0;

function locatedProfile(float $lat, float $lng, bool $share = true, array $attributes = []): Profile
{
    $user = userWithProfile($attributes);
    $profile = $user->personalProfile;
    $profile->forceFill([
        'lat' => $lat,
        'lng' => $lng,
        'geohash' => Geohash::encode($lat, $lng, 9),
        'share_location' => $share,
    ])->save();

    return $profile;
}

test('nearby users returns sharers within the radius and excludes those beyond it', function () {
    $me = userWithProfile();

    $near = locatedProfile(NU_LAT + 0.09, NU_LNG);
    $far = locatedProfile(NU_LAT + 0.45, NU_LNG);

    $res = $this->actingAs($me)
        ->getJson('/api/v1/geo/nearby-users?lat='.NU_LAT.'&lng='.NU_LNG.'&radius_km=25')
        ->assertOk();

    $ulids = collect($res->json('data'))->pluck('ulid');
    expect($ulids)->toContain($near->ulid)->not->toContain($far->ulid);
});

test('nearby users excludes non-sharers, self, and blocked profiles', function () {
    $me = userWithProfile();
    // Give the viewer a location too, to prove self is excluded.
    $me->personalProfile->forceFill([
        'lat' => NU_LAT, 'lng' => NU_LNG,
        'geohash' => Geohash::encode(NU_LAT, NU_LNG, 9), 'share_location' => true,
    ])->save();

    $sharer = locatedProfile(NU_LAT + 0.01, NU_LNG);
    $nonSharer = locatedProfile(NU_LAT + 0.01, NU_LNG, share: false);
    $blocked = locatedProfile(NU_LAT + 0.01, NU_LNG);

    app(SafetyService::class)->block($me->personalProfile, $blocked);

    $res = $this->actingAs($me)
        ->getJson('/api/v1/geo/nearby-users?lat='.NU_LAT.'&lng='.NU_LNG.'&radius_km=25')
        ->assertOk();

    $ulids = collect($res->json('data'))->pluck('ulid');
    expect($ulids)->toContain($sharer->ulid)
        ->not->toContain($nonSharer->ulid)
        ->not->toContain($blocked->ulid)
        ->not->toContain($me->personalProfile->ulid);
});

test('nearby users are ordered by ascending distance and expose distance_km', function () {
    $me = userWithProfile();

    $closest = locatedProfile(NU_LAT + 0.02, NU_LNG);
    $middle = locatedProfile(NU_LAT + 0.09, NU_LNG);
    $farthest = locatedProfile(NU_LAT + 0.18, NU_LNG);

    $res = $this->actingAs($me)
        ->getJson('/api/v1/geo/nearby-users?lat='.NU_LAT.'&lng='.NU_LNG.'&radius_km=100')
        ->assertOk();

    $rows = collect($res->json('data'));
    expect($rows->pluck('ulid')->all())->toBe([$closest->ulid, $middle->ulid, $farthest->ulid]);

    $distances = $rows->pluck('distance_km');
    expect($distances->all())->toBe($distances->sort()->values()->all());
    // distance_km is rounded to one decimal.
    expect($distances->first())->toBe(round($distances->first(), 1));
});

test('nearby users coordinates are fuzzed to three decimals', function () {
    $me = userWithProfile();
    $sharer = locatedProfile(NU_LAT + 0.0212345, NU_LNG + 0.0198765);

    $res = $this->actingAs($me)
        ->getJson('/api/v1/geo/nearby-users?lat='.NU_LAT.'&lng='.NU_LNG.'&radius_km=25')
        ->assertOk();

    $row = collect($res->json('data'))->firstWhere('ulid', $sharer->ulid);

    expect($row['lat'])->toBe(round(NU_LAT + 0.0212345, 3));
    expect($row['lng'])->toBe(round(NU_LNG + 0.0198765, 3));
});

test('nearby users validates coordinates and radius', function () {
    $me = userWithProfile();

    $this->actingAs($me)->getJson('/api/v1/geo/nearby-users')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lat', 'lng']);

    $this->actingAs($me)->getJson('/api/v1/geo/nearby-users?lat=0&lng=10&radius_km=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('radius_km');
});
