<?php

namespace App\Services\Geo;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Policy-compliant reverse-geocoding proxy for Nominatim (OpenStreetMap).
 *
 * Nominatim's usage policy requires (a) at most one request per second from a
 * single source, (b) a descriptive User-Agent with contact details, and (c)
 * aggressive caching of results. We honour all three: results are cached for
 * 30 days keyed by coordinates rounded to 3 decimals (~110 m), and a global
 * cache lock serialises outbound requests so we never exceed 1 req/s. Any
 * failure, contention timeout or disabled configuration yields null — this
 * method never throws.
 */
class GeocodingService
{
    private const CACHE_TTL_DAYS = 30;

    /** How long to wait for the global rate-limit lock before giving up. */
    private const LOCK_WAIT_SECONDS = 3;

    /** Minimum spacing between outbound Nominatim requests, in milliseconds. */
    private const MIN_INTERVAL_MS = 1000;

    private const LAST_REQUEST_KEY = 'geo:rev:last';

    /**
     * Reverse-geocode a coordinate to a coarse place descriptor.
     *
     * @return array{country_code: ?string, country: ?string, city: ?string, display_name: ?string}|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        if (! config('services.nominatim.enabled')) {
            return null;
        }

        $key = $this->cacheKey($lat, $lng);

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock('nominatim', 5);

        try {
            if (! $lock->block(self::LOCK_WAIT_SECONDS)) {
                return null;
            }
        } catch (LockTimeoutException) {
            return null;
        }

        try {
            // Another waiter may have populated the cache while we blocked.
            $cached = Cache::get($key);
            if ($cached !== null) {
                return $cached;
            }

            $this->throttle();

            $result = $this->fetch($lat, $lng);

            if ($result !== null) {
                Cache::put($key, $result, now()->addDays(self::CACHE_TTL_DAYS));
            }

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function cacheKey(float $lat, float $lng): string
    {
        return 'geo:rev:'.round($lat, 3).','.round($lng, 3);
    }

    /**
     * Enforce the 1 req/s ceiling by sleeping out the remainder of the
     * interval since the previous outbound request, if any.
     */
    private function throttle(): void
    {
        $last = Cache::get(self::LAST_REQUEST_KEY);
        $nowMs = (int) (microtime(true) * 1000);

        if (is_int($last)) {
            $elapsed = $nowMs - $last;
            if ($elapsed >= 0 && $elapsed < self::MIN_INTERVAL_MS) {
                usleep((self::MIN_INTERVAL_MS - $elapsed) * 1000);
            }
        }

        Cache::put(self::LAST_REQUEST_KEY, (int) (microtime(true) * 1000), now()->addMinutes(5));
    }

    /**
     * @return array{country_code: ?string, country: ?string, city: ?string, display_name: ?string}|null
     */
    private function fetch(float $lat, float $lng): ?array
    {
        try {
            $base = rtrim((string) config('services.nominatim.base_url'), '/');

            $response = Http::withHeaders([
                'User-Agent' => 'SBH-Community/1.0 (contact '.config('services.nominatim.contact').')',
            ])->timeout(5)->get($base.'/reverse', [
                'format' => 'jsonv2',
                'lat' => $lat,
                'lon' => $lng,
                'zoom' => 10,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! is_array($data)) {
                return null;
            }

            $address = $data['address'] ?? [];

            $countryCode = isset($address['country_code']) && $address['country_code'] !== ''
                ? strtoupper((string) $address['country_code'])
                : null;

            $city = $address['city']
                ?? $address['town']
                ?? $address['village']
                ?? $address['county']
                ?? null;

            return [
                'country_code' => $countryCode,
                'country' => $address['country'] ?? null,
                'city' => $city,
                'display_name' => $data['display_name'] ?? null,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
