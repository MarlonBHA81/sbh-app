<?php

use App\Services\Geo\GeocodingService;
use Illuminate\Support\Facades\Http;

function fakeNominatimSuccess(): void
{
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Sydney, New South Wales, Australia',
            'address' => [
                'city' => 'Sydney',
                'country' => 'Australia',
                'country_code' => 'au',
            ],
        ], 200),
    ]);
}

test('reverse returns a normalised place descriptor on success', function () {
    fakeNominatimSuccess();

    $result = app(GeocodingService::class)->reverse(-33.8688, 151.2093);

    expect($result)->toMatchArray([
        'country_code' => 'AU',
        'country' => 'Australia',
        'city' => 'Sydney',
        'display_name' => 'Sydney, New South Wales, Australia',
    ]);
});

test('reverse falls back through the city chain', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Somewhere rural',
            'address' => ['county' => 'Countyshire', 'country' => 'Nowhere', 'country_code' => 'nw'],
        ], 200),
    ]);

    $result = app(GeocodingService::class)->reverse(1.0, 2.0);

    expect($result['city'])->toBe('Countyshire');
});

test('reverse returns null on an upstream failure and never throws', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response('boom', 500),
    ]);

    expect(app(GeocodingService::class)->reverse(1.0, 2.0))->toBeNull();
});

test('reverse returns null and sends nothing when disabled', function () {
    config()->set('services.nominatim.enabled', false);
    Http::fake();

    expect(app(GeocodingService::class)->reverse(1.0, 2.0))->toBeNull();

    Http::assertNothingSent();
});

test('reverse caches results so a repeated lookup skips the network', function () {
    fakeNominatimSuccess();

    $geo = app(GeocodingService::class);
    $geo->reverse(-33.8688, 151.2093);
    $geo->reverse(-33.8688, 151.2093);

    Http::assertSentCount(1);
});

test('reverse rounds coordinates to three decimals so nearby points share a cache entry', function () {
    fakeNominatimSuccess();

    $geo = app(GeocodingService::class);
    $geo->reverse(-33.86881, 151.20931);
    // Differs only in the 4th decimal -> same cache key -> no second request.
    $geo->reverse(-33.86889, 151.20939);

    Http::assertSentCount(1);
});
