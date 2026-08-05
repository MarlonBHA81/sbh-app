<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Profile;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Vendor store management (Shop P1). A business profile's owner/managers create
 * and brand one store; personal profiles have no store.
 */
class StoreController extends Controller
{
    /** The active business profile's store (or null). */
    public function show(Request $request): JsonResponse
    {
        $profile = $this->vendorProfile($request);

        $store = $profile->store()->withCount('products')->first();

        return response()->json([
            'data' => $store ? new StoreResource($store) : null,
        ]);
    }

    /** Create or update (and brand) the store. */
    public function upsert(Request $request): JsonResponse
    {
        $profile = $this->vendorProfile($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'brand_color' => ['sometimes', 'nullable', 'string', 'max:9'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'max:9'],
            'policies' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $store = $profile->store;

        if ($store === null) {
            $store = new Store([
                'profile_id' => $profile->id,
                'slug' => $this->uniqueSlug($data['name']),
            ]);
        }

        $store->fill($data);
        $store->profile_id = $profile->id;
        $store->save();

        return response()->json(['data' => new StoreResource($store->fresh())], $store->wasRecentlyCreated ? 201 : 200);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $i = 1;

        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function vendorProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness(), 403, 'Only business profiles can run a store.');
        abort_unless($profile->canManageMembers($request->user()), 403);

        return $profile;
    }
}
