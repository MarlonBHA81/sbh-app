<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Profile;
use App\Models\Store;
use Illuminate\Support\Str;

/** Enable the PayFast driver with sandbox test credentials. */
function useCommercePayFast(): void
{
    config()->set('payments.driver', 'payfast');
    config()->set('payments.payfast.merchant_id', '10000100');
    config()->set('payments.payfast.merchant_key', '46f0cd694581a');
    config()->set('payments.payfast.passphrase', null);
    config()->set('payments.payfast.sandbox', true);
    config()->set('payments.platform_fee_percent', 10);
    config()->set('payments.frontend_url', 'https://app.test');
    config()->set('payments.api_url', 'https://api.test');
}

/** A store (optionally VAT-registered) with a single priced product. */
function commerceFixtures(array $storeAttributes = [], array $productAttributes = []): array
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();
    $store = Store::create(array_merge([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'Test Store',
        'is_active' => true,
    ], $storeAttributes));

    $product = $store->products()->create(array_merge([
        'type' => 'digital_download',
        'title' => 'Template pack',
        'description' => 'Handy templates.',
        'price_cents' => 10000,
        'is_published' => true,
    ], $productAttributes));

    return [$store, $product];
}

test('a product on sale checks out at the sale price', function () {
    useCommercePayFast();
    [, $product] = commerceFixtures(productAttributes: [
        'sale_price_cents' => 6000,
        'sale_ends_at' => now()->addDay(),
    ]);
    $buyer = userWithProfile();

    $res = $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertOk();

    $order = Order::query()->where('ulid', $res->json('data.order'))->firstOrFail();
    expect($order->total_cents)->toBe(6000);
});

test('an expired sale falls back to the full price', function () {
    [, $product] = commerceFixtures(productAttributes: [
        'sale_price_cents' => 6000,
        'sale_ends_at' => now()->subDay(),
    ]);

    expect($product->onSale())->toBeFalse()
        ->and($product->effectivePriceCents())->toBe(10000);
});

test('a percent coupon reduces the order total and records the redemption', function () {
    useCommercePayFast();
    [$store, $product] = commerceFixtures();
    $coupon = Coupon::create([
        'code' => 'save20',
        'store_id' => $store->id,
        'type' => Coupon::TYPE_PERCENT,
        'value' => 20,
    ]);
    $buyer = userWithProfile();

    $res = $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', [
            'product_ulid' => $product->ulid,
            'coupon_code' => 'SAVE20',
        ])
        ->assertOk();

    $order = Order::query()->where('ulid', $res->json('data.order'))->firstOrFail();
    expect($order->total_cents)->toBe(8000)
        ->and($order->discount_cents)->toBe(2000)
        ->and($order->coupon_id)->toBe($coupon->id);

    expect(CouponRedemption::where('coupon_id', $coupon->id)->count())->toBe(1);
    expect($coupon->fresh()->redeemed_count)->toBe(1);
});

test('a fixed coupon below min spend is rejected at checkout', function () {
    useCommercePayFast();
    [$store, $product] = commerceFixtures(productAttributes: ['price_cents' => 5000]);
    Coupon::create([
        'code' => 'BIG50',
        'store_id' => $store->id,
        'type' => Coupon::TYPE_FIXED,
        'value' => 5000,
        'min_spend_cents' => 8000,
    ]);
    $buyer = userWithProfile();

    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', [
            'product_ulid' => $product->ulid,
            'coupon_code' => 'BIG50',
        ])
        ->assertStatus(422);
});

test('a coupon cannot be redeemed twice by the same buyer', function () {
    useCommercePayFast();
    [$store, $product] = commerceFixtures();
    $coupon = Coupon::create([
        'code' => 'ONCE',
        'store_id' => $store->id,
        'type' => Coupon::TYPE_PERCENT,
        'value' => 10,
    ]);
    $buyer = userWithProfile();

    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid, 'coupon_code' => 'ONCE'])
        ->assertOk();

    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid, 'coupon_code' => 'ONCE'])
        ->assertStatus(422);
});

test('a coupon scoped to another store does not apply', function () {
    useCommercePayFast();
    [, $product] = commerceFixtures();
    [$otherStore] = commerceFixtures();
    Coupon::create([
        'code' => 'OTHERSTORE',
        'store_id' => $otherStore->id,
        'type' => Coupon::TYPE_PERCENT,
        'value' => 50,
    ]);
    $buyer = userWithProfile();

    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid, 'coupon_code' => 'OTHERSTORE'])
        ->assertStatus(422);
});

test('a VAT-registered store records the inclusive VAT portion on the order', function () {
    useCommercePayFast();
    [, $product] = commerceFixtures(storeAttributes: [
        'is_vat_registered' => true,
        'vat_rate_bp' => 1500,
    ]);
    $buyer = userWithProfile();

    $res = $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertOk();

    $order = Order::query()->where('ulid', $res->json('data.order'))->firstOrFail();
    // 10000 inclusive of 15% VAT → VAT = 10000 - 10000/1.15 = 1304.
    expect($order->total_cents)->toBe(10000)
        ->and($order->vat_cents)->toBe(1304)
        ->and($order->vat_rate_bp)->toBe(1500);
});

test('the quote endpoint previews the total without creating an order', function () {
    [$store, $product] = commerceFixtures(storeAttributes: [
        'is_vat_registered' => true,
        'vat_rate_bp' => 1500,
    ]);
    Coupon::create([
        'code' => 'PREVIEW10',
        'store_id' => $store->id,
        'type' => Coupon::TYPE_PERCENT,
        'value' => 10,
    ]);
    $buyer = userWithProfile();

    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout/quote', [
            'product_ulid' => $product->ulid,
            'coupon_code' => 'PREVIEW10',
        ])
        ->assertOk()
        ->assertJsonPath('data.subtotal_cents', 10000)
        ->assertJsonPath('data.discount_cents', 1000)
        ->assertJsonPath('data.total_cents', 9000)
        ->assertJsonPath('data.coupon_applied', true)
        // 9000 inclusive of 15% VAT → 1174.
        ->assertJsonPath('data.vat_cents', 1174);

    expect(Order::count())->toBe(0);
});

test('the quote flags an invalid coupon without failing', function () {
    [, $product] = commerceFixtures();
    $buyer = userWithProfile();

    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout/quote', [
            'product_ulid' => $product->ulid,
            'coupon_code' => 'NOPE',
        ])
        ->assertOk()
        ->assertJsonPath('data.coupon_applied', false)
        ->assertJsonPath('data.coupon_invalid', true);
});
