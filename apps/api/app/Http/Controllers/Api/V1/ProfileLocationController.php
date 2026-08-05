<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\Geo\GeocodingService;
use App\Support\Geohash;
use Illuminate\Http\Request;

class ProfileLocationController extends Controller
{
    /**
     * Opt the profile into location sharing at a precise coordinate. The
     * geohash is derived locally; country/city are resolved inline via the
     * geocoding proxy but tolerate a null result (nothing overwritten).
     */
    public function store(Request $request, Profile $profile, GeocodingService $geo): ProfileResource
    {
        abort_unless($profile->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        $place = $geo->reverse($lat, $lng);

        $profile->fill([
            'lat' => $lat,
            'lng' => $lng,
            'geohash' => Geohash::encode($lat, $lng, 9),
            'share_location' => true,
            'country_code' => $place['country_code'] ?? $profile->country_code,
            'city' => $place['city'] ?? $profile->city,
        ])->save();

        return new ProfileResource($profile->refresh());
    }

    /**
     * Clear the shared location. The country code is retained (it is coarse
     * enough to keep powering the country-scope local feed).
     */
    public function destroy(Request $request, Profile $profile): ProfileResource
    {
        abort_unless($profile->user_id === $request->user()->id, 403);

        $profile->fill([
            'lat' => null,
            'lng' => null,
            'geohash' => null,
            'city' => null,
            'share_location' => false,
        ])->save();

        return new ProfileResource($profile->refresh());
    }
}
