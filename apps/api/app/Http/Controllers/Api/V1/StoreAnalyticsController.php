<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\Analytics\StoreAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor store analytics (Shop P4) — views, sales and conversion over a window.
 * Gated to the owning business profile's managers.
 */
class StoreAnalyticsController extends Controller
{
    public function show(Request $request, StoreAnalyticsService $analytics): JsonResponse
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness() && $profile->canManageMembers($request->user()), 403);

        $store = $profile->store;
        abort_unless($store !== null, 422, 'Create your store first.');

        $days = (int) $request->integer('days', 30);

        return response()->json(['data' => $analytics->forStore($store, $days)]);
    }
}
