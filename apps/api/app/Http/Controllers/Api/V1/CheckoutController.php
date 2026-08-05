<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Purchase;
use App\Services\Payments\PaymentGateway;
use App\Services\Shop\CheckoutPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace checkout (Shop P2). Builds a pending order (primary product +
 * optional bumps, sale prices, coupon and inclusive VAT), then returns the
 * provider redirect. Payment is confirmed later by the ITN webhook — never here.
 */
class CheckoutController extends Controller
{
    /**
     * How long an unpaid order stays reusable by an identical re-submission.
     * Long enough to cover a retry or a trip to the payment page and back;
     * short enough that a genuine later purchase attempt starts fresh.
     */
    private const PENDING_REUSE_MINUTES = 60;

    public function __construct(private CheckoutPricing $pricing) {}

    public function store(Request $request, PaymentGateway $gateway): JsonResponse
    {
        abort_unless($gateway->enabled(), 422, 'Payments are not configured yet.');

        $buyer = $this->activeProfile($request);

        $data = $request->validate([
            'product_ulid' => ['required', 'string'],
            'bump_ulids' => ['sometimes', 'array', 'max:5'],
            'bump_ulids.*' => ['string'],
            'coupon_code' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $product = $this->resolveProduct($data['product_ulid']);

        // Entitlements are unique per (buyer, product) — markPaid() grants via
        // Purchase::firstOrCreate — so buying something already owned charges
        // the buyer again and silently grants nothing. Refuse it outright.
        abort_if(
            Purchase::ownedBy($buyer, $product),
            409,
            'You already own this — check your purchases.',
        );

        $bumps = $this->resolveBumps($product, $data['bump_ulids'] ?? []);

        $coupon = null;
        if (! empty($data['coupon_code'])) {
            $coupon = Coupon::query()->where('code', strtoupper(trim($data['coupon_code'])))->first();
            abort_if($coupon === null, 422, 'That coupon code is not valid.');
        }

        $quote = $this->pricing->quote($product, $bumps, $coupon, $buyer);

        // A supplied code that resolves but can't be applied is a hard error at
        // checkout (the buyer explicitly entered it) — the quote endpoint is the
        // place to preview validity without failing.
        abort_if($coupon !== null && $quote['coupon'] === null, 422, 'That coupon can’t be applied to this order.');

        // Re-submitting the same checkout (double-click, client retry, back
        // button) previously minted a second order the buyer could also go on
        // to pay — two charges, one entitlement. Hand back the equivalent
        // pending order instead. A genuinely different basket changes the total
        // or the coupon, so it still gets a fresh order.
        $existing = $this->equivalentPendingOrder($buyer, $product, $quote);

        $order = $existing ?? DB::transaction(function () use ($buyer, $product, $quote) {
            $order = Order::create([
                'buyer_profile_id' => $buyer->id,
                'store_id' => $product->store_id,
                'coupon_id' => $quote['coupon']?->id,
                'status' => Order::STATUS_PENDING,
                'total_cents' => $quote['total_cents'],
                'discount_cents' => $quote['discount_cents'],
                'vat_cents' => $quote['vat_cents'],
                'vat_rate_bp' => $quote['vat_rate_bp'],
                'currency' => $product->currency,
            ]);

            foreach ($quote['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'title' => $item['title'],
                    'unit_cents' => $item['unit_cents'],
                    'kind' => $item['kind'],
                ]);
            }

            if ($quote['coupon'] !== null) {
                CouponRedemption::create([
                    'coupon_id' => $quote['coupon']->id,
                    'order_id' => $order->id,
                    'buyer_profile_id' => $buyer->id,
                ]);
                $quote['coupon']->increment('redeemed_count');
            }

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

    /**
     * Dry-run price preview (sale prices, coupon, VAT) without creating an order
     * — powers the checkout box so the buyer sees the total before paying.
     */
    public function quote(Request $request): JsonResponse
    {
        $buyer = $this->activeProfile($request);

        $data = $request->validate([
            'product_ulid' => ['required', 'string'],
            'bump_ulids' => ['sometimes', 'array', 'max:5'],
            'bump_ulids.*' => ['string'],
            'coupon_code' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $product = $this->resolveProduct($data['product_ulid']);
        $bumps = $this->resolveBumps($product, $data['bump_ulids'] ?? []);

        $couponCode = ! empty($data['coupon_code']) ? strtoupper(trim($data['coupon_code'])) : null;
        $coupon = $couponCode !== null
            ? Coupon::query()->where('code', $couponCode)->first()
            : null;

        $quote = $this->pricing->quote($product, $bumps, $coupon, $buyer);

        return response()->json([
            'data' => [
                'subtotal_cents' => $quote['subtotal_cents'],
                'discount_cents' => $quote['discount_cents'],
                'total_cents' => $quote['total_cents'],
                'vat_cents' => $quote['vat_cents'],
                'vat_rate_bp' => $quote['vat_rate_bp'],
                'currency' => $product->currency,
                'coupon_applied' => $quote['coupon'] !== null,
                // Distinguish "no code given" from "code given but rejected".
                'coupon_invalid' => $couponCode !== null && $quote['coupon'] === null,
            ],
        ]);
    }

    /** Order status (for the success page to poll after redirect). */
    public function show(Request $request, Order $order): JsonResponse
    {
        $buyer = $this->activeProfile($request);
        abort_unless($order->buyer_profile_id === $buyer->id, 403);

        // The primary item's product, so the success page can offer its upsells.
        $order->loadMissing('items.product');
        $primary = $order->items->firstWhere('kind', 'item')?->product;

        return response()->json([
            'data' => [
                'ulid' => $order->ulid,
                'status' => $order->status,
                'total_cents' => $order->total_cents,
                'discount_cents' => $order->discount_cents,
                'vat_cents' => $order->vat_cents,
                'currency' => $order->currency,
                'product_ulid' => $primary?->ulid,
            ],
        ]);
    }

    /**
     * A still-payable pending order from this buyer for the same basket.
     *
     * Matched on product + total + coupon rather than a client-supplied
     * idempotency key, so it protects against retries the client never labelled
     * (double-click, back button, a flaky connection retrying the POST).
     *
     * @param  array<string, mixed>  $quote
     */
    private function equivalentPendingOrder(Profile $buyer, Product $product, array $quote): ?Order
    {
        $couponId = $quote['coupon']?->id;

        $order = Order::query()
            ->where('buyer_profile_id', $buyer->id)
            ->where('status', Order::STATUS_PENDING)
            ->where('total_cents', $quote['total_cents'])
            // `where('coupon_id', null)` compiles to `= NULL`, which never
            // matches — the no-coupon case must use whereNull.
            ->when(
                $couponId === null,
                fn ($q) => $q->whereNull('coupon_id'),
                fn ($q) => $q->where('coupon_id', $couponId),
            )
            ->where('created_at', '>=', now()->subMinutes(self::PENDING_REUSE_MINUTES))
            ->whereHas('items', fn ($q) => $q
                ->where('product_id', $product->id)
                ->where('kind', OrderItem::KIND_ITEM))
            ->latest('id')
            ->first();

        // checkout() reads the buyer's email and the item name off the order.
        return $order?->loadMissing('items', 'buyer.user');
    }

    private function resolveProduct(string $ulid): Product
    {
        $product = Product::query()->visible()->where('ulid', $ulid)->firstOrFail();
        abort_if($product->isFree(), 422, 'This product is not for sale.');
        $product->loadMissing('store');

        return $product;
    }

    /**
     * @param  array<int, string>  $bumpUlids
     * @return Collection<int, Product>
     */
    private function resolveBumps(Product $product, array $bumpUlids): Collection
    {
        if (empty($bumpUlids)) {
            return collect();
        }

        // Bumps must be configured bumps on this product (and visible).
        $bumpIds = $product->offers()->where('kind', Product::OFFER_BUMP)->pluck('related_product_id');

        return Product::query()->visible()
            ->whereIn('ulid', $bumpUlids)
            ->whereIn('id', $bumpIds)
            ->get();
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
