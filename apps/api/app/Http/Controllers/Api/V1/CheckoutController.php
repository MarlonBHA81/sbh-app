<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Profile;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace checkout (Shop P2). Builds a pending order (primary product +
 * optional bumps), then returns the provider redirect. Payment is confirmed
 * later by the ITN webhook — never here.
 */
class CheckoutController extends Controller
{
    public function store(Request $request, PaymentGateway $gateway): JsonResponse
    {
        abort_unless($gateway->enabled(), 422, 'Payments are not configured yet.');

        $buyer = $this->activeProfile($request);

        $data = $request->validate([
            'product_ulid' => ['required', 'string'],
            'bump_ulids' => ['sometimes', 'array', 'max:5'],
            'bump_ulids.*' => ['string'],
        ]);

        $product = Product::query()->visible()->where('ulid', $data['product_ulid'])->firstOrFail();
        abort_if($product->price_cents === null || $product->price_cents <= 0, 422, 'This product is not for sale.');

        // Bumps must be configured bumps on this product (and visible).
        $bumpIds = $product->offers()->where('kind', Product::OFFER_BUMP)->pluck('related_product_id');
        $bumps = empty($data['bump_ulids'])
            ? collect()
            : Product::query()->visible()
                ->whereIn('ulid', $data['bump_ulids'])
                ->whereIn('id', $bumpIds)
                ->get();

        $order = DB::transaction(function () use ($buyer, $product, $bumps) {
            $order = Order::create([
                'buyer_profile_id' => $buyer->id,
                'store_id' => $product->store_id,
                'status' => Order::STATUS_PENDING,
                'total_cents' => 0,
                'currency' => $product->currency,
            ]);

            $total = (int) $product->price_cents;
            $order->items()->create([
                'product_id' => $product->id,
                'title' => $product->title,
                'unit_cents' => $product->price_cents,
                'kind' => OrderItem::KIND_ITEM,
            ]);

            foreach ($bumps as $bump) {
                $offer = $product->offers()
                    ->where('kind', Product::OFFER_BUMP)
                    ->where('related_product_id', $bump->id)
                    ->first();
                $price = max(0, (int) ($bump->price_cents ?? 0) - (int) ($offer?->discount_cents ?? 0));
                $total += $price;

                $order->items()->create([
                    'product_id' => $bump->id,
                    'title' => $bump->title,
                    'unit_cents' => $price,
                    'kind' => OrderItem::KIND_BUMP,
                ]);
            }

            $order->update(['total_cents' => $total]);

            return $order->load('items', 'buyer.user');
        });

        $redirect = $gateway->checkout($order);

        return response()->json([
            'data' => [
                'order' => $order->ulid,
                'process_url' => $redirect['process_url'],
                'fields' => $redirect['fields'],
            ],
        ]);
    }

    /** Order status (for the success page to poll after redirect). */
    public function show(Request $request, Order $order): JsonResponse
    {
        $buyer = $this->activeProfile($request);
        abort_unless($order->buyer_profile_id === $buyer->id, 403);

        return response()->json([
            'data' => [
                'ulid' => $order->ulid,
                'status' => $order->status,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
            ],
        ]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
