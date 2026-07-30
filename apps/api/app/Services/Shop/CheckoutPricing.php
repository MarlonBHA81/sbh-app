<?php

namespace App\Services\Shop;

use App\Models\Coupon;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Single source of truth for what a checkout costs: line items (with sale
 * prices), coupon discount, and the inclusive VAT portion. Used by both the
 * dry-run quote endpoint and the real order build so the two never diverge.
 */
class CheckoutPricing
{
    /**
     * @param  Collection<int, Product>  $bumps
     * @return array{
     *     items: list<array{product: Product, title: string, unit_cents: int, kind: string}>,
     *     subtotal_cents: int,
     *     discount_cents: int,
     *     total_cents: int,
     *     vat_cents: int,
     *     vat_rate_bp: int,
     *     coupon: Coupon|null
     * }
     */
    public function quote(Product $product, Collection $bumps, ?Coupon $coupon, Profile $buyer): array
    {
        $store = $product->store;

        $items = [[
            'product' => $product,
            'title' => $product->title,
            'unit_cents' => (int) $product->effectivePriceCents(),
            'kind' => OrderItem::KIND_ITEM,
        ]];

        foreach ($bumps as $bump) {
            $offer = $product->offers()
                ->where('kind', Product::OFFER_BUMP)
                ->where('related_product_id', $bump->id)
                ->first();
            $unit = max(0, (int) $bump->effectivePriceCents() - (int) ($offer?->discount_cents ?? 0));

            $items[] = [
                'product' => $bump,
                'title' => $bump->title,
                'unit_cents' => $unit,
                'kind' => OrderItem::KIND_BUMP,
            ];
        }

        $subtotal = array_sum(array_column($items, 'unit_cents'));

        $appliedCoupon = $coupon !== null && $this->couponUsable($coupon, $store, $subtotal, $buyer)
            ? $coupon
            : null;
        $discount = $appliedCoupon?->discountFor($subtotal) ?? 0;

        $total = max(0, $subtotal - $discount);
        $vatRateBp = $store->is_vat_registered ? (int) $store->vat_rate_bp : 0;
        $vat = $store->inclusiveVatCents($total);

        return [
            'items' => $items,
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'total_cents' => $total,
            'vat_cents' => $vat,
            'vat_rate_bp' => $vatRateBp,
            'coupon' => $appliedCoupon,
        ];
    }

    /** Whether a coupon can be redeemed by this buyer for this store/subtotal. */
    public function couponUsable(Coupon $coupon, Store $store, int $subtotalCents, Profile $buyer): bool
    {
        if (! $coupon->isLive() || ! $coupon->appliesToStore($store->id)) {
            return false;
        }

        if ($coupon->discountFor($subtotalCents) <= 0) {
            return false;
        }

        // One redemption per buyer.
        return ! $coupon->redemptions()
            ->where('buyer_profile_id', $buyer->id)
            ->exists();
    }
}
