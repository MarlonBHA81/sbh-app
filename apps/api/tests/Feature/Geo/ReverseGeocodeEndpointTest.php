<?php

use Illuminate\Support\Facades\Http;

test('reverse geocode endpoint wraps the service result', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Berlin, Germany',
            'address' => ['city' => 'Berlin', 'country' => 'Germany', 'country_code' => 'de'],
        ], 200),
    ]);

    $me = userWithProfile();

    $this->actingAs($me)->getJson('/api/v1/geo/reverse?lat=52.52&lng=13.405')
        ->assertOk()
        ->assertJsonPath('data.country_code', 'DE')
        ->assertJsonPath('data.city', 'Berlin');
});

test('reverse geocode endpoint returns null data when the service yields nothing', function () {
    config()->set('services.nominatim.enabled', false);

    $me = userWithProfile();

    $this->actingAs($me)->getJson('/api/v1/geo/reverse?lat=52.52&lng=13.405')
        ->assertOk()
        ->assertJsonPath('data', null);
});

test('reverse geocode endpoint validates coordinates', function () {
    $me = userWithProfile();

    $this->actingAs($me)->getJson('/api/v1/geo/reverse?lat=200&lng=13.405')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lat');
});

test('reverse geocode endpoint requires authentication', function () {
    $this->getJson('/api/v1/geo/reverse?lat=1&lng=2')->assertUnauthorized();
});
