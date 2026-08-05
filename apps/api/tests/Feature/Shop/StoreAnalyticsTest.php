<?php

use App\Models\Order;
use App\Models\Profile;
use App\Models\Store;
use Illuminate\Support\Str;

function analyticsStore(): array
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();
    $store = Store::create([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'Shop', 'is_active' => true,
    ]);
    $product = $store->products()->create([
        'type' => 'digital_download', 'title' => 'Pack', 'description' => 'x',
        'price_cents' => 10000, 'is_published' => true,
    ]);

    return [$owner, $business, $store, $product];
}

test('a shopper view is recorded once per day and counts in analytics', function () {
    [$owner, $business, $store, $product] = analyticsStore();

    $shopper = userWithProfile();
    // Two reports of the same store+product on the same day → one view each.
    foreach (range(1, 2) as $ignored) {
        $this->actingAs($shopper)
            ->postJson('/api/v1/shop/seen', [
                'store' => $store->slug,
                'products' => [$product->ulid],
            ])
            ->assertNoContent();
    }

    $this->flushHeaders();
    $res = $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me/store/analytics?days=30')
        ->assertOk()
        ->assertJsonPath('data.totals.views', 1);

    expect($res->json('data.top_products.0.title'))->toBe('Pack')
        ->and($res->json('data.top_products.0.views'))->toBe(1)
        ->and($res->json('data.series'))->toHaveCount(30);
});

test('analytics combine views and paid sales into conversion', function () {
    [$owner, $business, $store, $product] = analyticsStore();

    // One view.
    $shopper = userWithProfile();
    $this->actingAs($shopper)
        ->postJson('/api/v1/shop/seen', ['store' => $store->slug])
        ->assertNoContent();

    // One paid order.
    $buyer = userWithProfile();
    $order = Order::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'store_id' => $store->id,
        'status' => Order::STATUS_PENDING,
        'total_cents' => 10000,
        'currency' => 'ZAR',
    ]);
    $order->items()->create(['product_id' => $product->id, 'title' => 'Pack', 'unit_cents' => 10000, 'kind' => 'item']);
    config()->set('payments.platform_fee_percent', 10);
    $order->markPaid();

    $this->flushHeaders();
    $res = $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me/store/analytics?days=7')
        ->assertOk()
        ->assertJsonPath('data.totals.views', 1)
        ->assertJsonPath('data.totals.orders', 1)
        ->assertJsonPath('data.totals.gross_cents', 10000)
        ->assertJsonPath('data.totals.earnings_cents', 9000);

    // 1 order / 1 view = 100% (JSON drops the .0, so compare loosely).
    expect((float) $res->json('data.totals.conversion_pct'))->toBe(100.0);
});

test('store analytics require a business manager', function () {
    [$owner, $business, $store, $product] = analyticsStore();

    $stranger = userWithProfile();
    $this->actingAs($stranger)
        ->getJson('/api/v1/me/store/analytics')
        ->assertStatus(403);
});
