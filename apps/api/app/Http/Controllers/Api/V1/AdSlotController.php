<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdSlot;
use App\Services\Ads\AdTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdSlotController extends Controller
{
    public function show(string $placement, AdTrackingService $tracking): JsonResponse|Response
    {
        abort_unless(
            in_array($placement, [AdSlot::PLACEMENT_RIGHT_RAIL, AdSlot::PLACEMENT_FEED_INLINE], true),
            404,
        );

        $slot = $tracking->pickSlot($placement);

        if ($slot === null) {
            return response()->noContent();
        }

        return response()->json([
            'data' => [
                'key' => $slot->key,
                'name' => $slot->name,
                'sponsor_name' => $slot->sponsor_name,
                'sponsor_url' => $slot->sponsor_url,
                'image_url' => $slot->imageUrl(),
                'body' => $slot->body,
            ],
        ]);
    }
}
