<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Support\Str;

/** Enable the PayFast driver with sandbox test credentials. */
function usePayFast(): void
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

function shopFixtures(): array
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();
    $store = Store::create([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'Test Store',
        'is_active' => true,
    ]);

    $product = $store->products()->create([
        'type' => 'digital_download',
        'title' => 'Template pack',
        'description' => 'Handy templates.',
        'price_cents' => 9900,
        'is_published' => true,
    ]);

    return [$store, $product];
}

test('checkout creates a pending order and returns the PayFast redirect', function () {
    usePayFast();
    [$store, $product] = shopFixtures();

    $buyer = userWithProfile();

    $res = $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertOk();

    $orderUlid = $res->json('data.order');
    $order = Order::query()->where('ulid', $orderUlid)->firstOrFail();

    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->total_cents)->toBe(9900)
        ->and($order->store_id)->toBe($store->id)
        ->and($order->items)->toHaveCount(1);

    $res->assertJsonPath('data.process_url', 'https://sandbox.payfast.co.za/eng/process')
        ->assertJsonPath('data.fields.amount', '99.00')
        ->assertJsonPath('data.fields.m_payment_id', $order->reference)
        ->assertJsonPath('data.fields.merchant_id', '10000100');

    expect($res->json('data.fields.signature'))->toMatch('/^[a-f0-9]{32}$/');
});

test('checkout includes an order bump in the total', function () {
    usePayFast();
    [$store, $product] = shopFixtures();

    $bump = $store->products()->create([
        'type' => 'digital_download',
        'title' => 'Bonus checklist',
        'description' => 'Add-on.',
        'price_cents' => 4900,
        'is_published' => true,
    ]);
    ProductOffer::create([
        'product_id' => $product->id,
        'related_product_id' => $bump->id,
        'kind' => Product::OFFER_BUMP,
        'discount_cents' => 1000,
    ]);

    $buyer = userWithProfile();

    $res = $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', [
            'product_ulid' => $product->ulid,
            'bump_ulids' => [$bump->ulid],
        ])
        ->assertOk();

    $order = Order::query()->where('ulid', $res->json('data.order'))->firstOrFail();

    // 9900 + (4900 - 1000 discount) = 13800
    expect($order->total_cents)->toBe(13800)
        ->and($order->items)->toHaveCount(2);

    $res->assertJsonPath('data.fields.amount', '138.00');
});

test('a product not offered as a bump is ignored at checkout', function () {
    usePayFast();
    [$store, $product] = shopFixtures();

    // A published product that is NOT a bump on the primary.
    $other = $store->products()->create([
        'type' => 'digital_download',
        'title' => 'Unrelated',
        'description' => 'x',
        'price_cents' => 5000,
        'is_published' => true,
    ]);

    $buyer = userWithProfile();

    $res = $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', [
            'product_ulid' => $product->ulid,
            'bump_ulids' => [$other->ulid],
        ])
        ->assertOk();

    $order = Order::query()->where('ulid', $res->json('data.order'))->firstOrFail();

    expect($order->total_cents)->toBe(9900)
        ->and($order->items)->toHaveCount(1);
});

test('a free product cannot be checked out', function () {
    usePayFast();
    [$store] = shopFixtures();
    $free = $store->products()->create([
        'type' => 'digital_download',
        'title' => 'Freebie',
        'description' => 'x',
        'price_cents' => 0,
        'is_published' => true,
    ]);

    $buyer = userWithProfile();
    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $free->ulid])
        ->assertStatus(422);
});

test('checkout is unavailable when no payment driver is configured', function () {
    config()->set('payments.driver', 'null');
    [$store, $product] = shopFixtures();

    $buyer = userWithProfile();
    $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertStatus(422);

    expect(Order::query()->count())->toBe(0);
});

test('the buyer can poll their own order status', function () {
    usePayFast();
    [$store, $product] = shopFixtures();
    $buyer = userWithProfile();

    $order = $this->actingAs($buyer)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->json('data.order');

    $this->actingAs($buyer)
        ->getJson("/api/v1/shop/orders/{$order}")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_PENDING);

    // A different buyer cannot see it.
    $stranger = userWithProfile();
    $this->actingAs($stranger)
        ->getJson("/api/v1/shop/orders/{$order}")
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Double-charge regressions (audit CB-6)
|--------------------------------------------------------------------------
| Entitlements are unique per (buyer, product), so markPaid()'s
| Purchase::firstOrCreate silently no-ops a second grant. Without these guards
| a buyer could pay twice and receive nothing the second time.
*/

test('a buyer who already owns a product cannot check out again', function () {
    usePayFast();
    [$store, $product] = shopFixtures();

    $buyer = userWithProfile();
    $buyerProfile = $buyer->profiles()->first();

    Purchase::create([
        'buyer_profile_id' => $buyerProfile->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($buyer)
        ->withHeader('X-Profile-Id', $buyerProfile->ulid)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertStatus(409);

    expect(Order::query()->count())->toBe(0);
});

test('re-submitting the same checkout reuses the pending order instead of creating another', function () {
    usePayFast();
    [$store, $product] = shopFixtures();

    $buyer = userWithProfile();
    $buyerProfile = $buyer->profiles()->first();

    $first = $this->actingAs($buyer)
        ->withHeader('X-Profile-Id', $buyerProfile->ulid)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertOk()
        ->json('data.order');

    $second = $this->actingAs($buyer)
        ->withHeader('X-Profile-Id', $buyerProfile->ulid)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertOk()
        ->json('data.order');

    // Same order handed back, and only one payable order exists.
    expect($second)->toBe($first)
        ->and(Order::query()->count())->toBe(1);
});

test('a different basket still creates its own order', function () {
    usePayFast();
    [$store, $product] = shopFixtures();

    $bump = $store->products()->create([
        'type' => 'digital_download', 'title' => 'Bonus', 'description' => 'x',
        'price_cents' => 2500, 'is_published' => true,
    ]);
    ProductOffer::create([
        'product_id' => $product->id,
        'related_product_id' => $bump->id,
        'kind' => Product::OFFER_BUMP,
        'discount_cents' => 0,
    ]);

    $buyer = userWithProfile();
    $buyerProfile = $buyer->profiles()->first();

    $this->actingAs($buyer)->withHeader('X-Profile-Id', $buyerProfile->ulid)
        ->postJson('/api/v1/shop/checkout', ['product_ulid' => $product->ulid])
        ->assertOk();

    // Adding a bump changes the total, so this is a genuinely different basket.
    $this->actingAs($buyer)->withHeader('X-Profile-Id', $buyerProfile->ulid)
        ->postJson('/api/v1/shop/checkout', [
            'product_ulid' => $product->ulid,
            'bump_ulids' => [$bump->ulid],
        ])
        ->assertOk();

    expect(Order::query()->count())->toBe(2);
});
