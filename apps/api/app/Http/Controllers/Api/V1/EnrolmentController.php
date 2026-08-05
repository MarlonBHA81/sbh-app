<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Purchase;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Free enrolment (Shop P3). Grants the Purchase entitlement for a R0 product
 * (lead-magnet courses / free tools) without going through PayFast. Paid
 * products must still be bought via checkout.
 */
class EnrolmentController extends Controller
{
    public function store(Request $request, Product $product, WebhookDispatcher $webhooks): JsonResponse
    {
        $profile = $request->attributes->get('activeProfile');
        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        abort_unless($product->is_published && $product->store()->where('is_active', true)->exists(), 404);
        abort_unless($product->isFree(), 422, 'This product must be purchased.');

        $purchase = Purchase::query()->firstOrCreate(
            ['buyer_profile_id' => $profile->id, 'product_id' => $product->id],
            ['order_id' => null],
        );

        // Only announce the entitlement the first time (keeps CRM idempotent).
        if ($purchase->wasRecentlyCreated) {
            $webhooks->contact(WebhookDispatcher::PURCHASE_COMPLETED, $profile, [
                'product' => $product->ulid,
                'title' => $product->title,
                'total_cents' => 0,
                'currency' => $product->currency,
            ]);
        }

        return response()->json(['data' => ['enrolled' => true]]);
    }
}
