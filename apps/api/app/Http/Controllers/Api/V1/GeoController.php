<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\Geo\GeocodingService;
use App\Services\SafetyService;
use App\Support\Geohash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    /** Same guarded spherical-law-of-cosines expression the nearby feed uses. */
    private const HAVERSINE = '(6371 * acos(min(1.0, max(-1.0,'
        .' cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?))'
        .' + sin(radians(?)) * sin(radians(lat))))))';

    public function __construct(private SafetyService $safety) {}

    /**
     * Reverse-geocode a coordinate through the policy-compliant proxy.
     */
    public function reverse(Request $request, GeocodingService $geo): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'data' => $geo->reverse((float) $validated['lat'], (float) $validated['lng']),
        ]);
    }

    /**
     * Profiles that opted into location sharing within a radius, ordered by
     * distance. Coordinates are fuzzed to 3 decimals (~110 m) for privacy.
     */
    public function nearbyUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'between:1,100'],
        ]);

        /** @var Profile $viewer */
        $viewer = $request->attributes->get('activeProfile');
        abort_unless($viewer instanceof Profile, 422, 'No active profile.');

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radiusKm = (float) ($validated['radius_km'] ?? 25);

        $prefixes = Geohash::coverRadius($lat, $lng, $radiusKm);

        $excluded = $this->safety->blockedProfileIds($viewer);
        $excluded[] = $viewer->id;

        $profiles = Profile::query()
            ->where('share_location', true)
            ->whereNotNull('geohash')
            ->whereNotIn('id', $excluded)
            ->where(function (Builder $query) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('geohash', 'like', $prefix.'%');
                }
            })
            ->selectRaw('*, '.self::HAVERSINE.' as distance_km', [$lat, $lng, $lat])
            // cast(? as real): PDO binds the radius as text and SQLite ranks
            // any number below any text, which would defeat the comparison.
            ->whereRaw(self::HAVERSINE.' <= cast(? as real)', [$lat, $lng, $lat, $radiusKm])
            ->orderBy('distance_km')
            ->limit(100)
            ->get();

        $data = $profiles->map(fn (Profile $profile) => [
            'ulid' => $profile->ulid,
            'handle' => $profile->handle,
            'name' => $profile->name,
            'avatar_url' => $profile->avatarUrl(),
            'lat' => round((float) $profile->lat, 3),
            'lng' => round((float) $profile->lng, 3),
            'distance_km' => round((float) $profile->distance_km, 1),
        ])->values();

        return response()->json(['data' => $data]);
    }
}
