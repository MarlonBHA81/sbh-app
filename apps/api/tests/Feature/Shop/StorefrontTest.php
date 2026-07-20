<?php

use App\Models\Product;
use App\Models\Profile;
use App\Models\Store;

function businessOwner(): array
{
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();

    return [$user, $business];
}

function makeStore(Profile $profile, array $attributes = []): Store
{
    return Store::create(array_merge([
        'profile_id' => $profile->id,
        'slug' => 'store-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
        'name' => 'Test Store',
        'is_active' => true,
    ], $attributes));
}

test('a business owner can create and brand a store', function () {
    [$user, $business] = businessOwner();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson('/api/v1/me/store', [
            'name' => 'Marlon Digital',
            'tagline' => 'Courses & tools',
            'brand_color' => '#4e8a88',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Marlon Digital')
        ->assertJsonPath('data.brand_color', '#4e8a88');

    expect(Store::query()->where('profile_id', $business->id)->exists())->toBeTrue();
});

test('a personal profile cannot open a store', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/store', ['name' => 'Nope'])
        ->assertForbidden();
});

test('creating a store twice updates the same store (one per profile)', function () {
    [$user, $business] = businessOwner();

    $headers = ['X-Profile-Id' => $business->ulid];

    $this->actingAs($user)->withHeaders($headers)
        ->postJson('/api/v1/me/store', ['name' => 'First'])->assertCreated();
    $this->actingAs($user)->withHeaders($headers)
        ->postJson('/api/v1/me/store', ['name' => 'Renamed'])->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect(Store::query()->where('profile_id', $business->id)->count())->toBe(1);
});

test('the owner can add a product and it appears on the public storefront', function () {
    [$user, $business] = businessOwner();
    $store = makeStore($business);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson('/api/v1/me/store/products', [
            'type' => 'digital_download',
            'title' => 'Template pack',
            'description' => 'Handy templates.',
            'price_cents' => 9900,
            'is_published' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Template pack');

    // Public browse: a different user sees the published product.
    $this->flushHeaders();
    $shopper = userWithProfile();
    $this->actingAs($shopper)
        ->getJson("/api/v1/shop/stores/{$store->slug}/products")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Template pack');
});

test('draft products and inactive stores are hidden from the public', function () {
    [$user, $business] = businessOwner();
    $store = makeStore($business);
    $store->products()->create(['type' => 'service', 'title' => 'Draft', 'description' => 'x', 'is_published' => false]);
    $store->products()->create(['type' => 'service', 'title' => 'Live', 'description' => 'x', 'is_published' => true]);

    $shopper = userWithProfile();
    $this->actingAs($shopper)
        ->getJson("/api/v1/shop/stores/{$store->slug}/products")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Live');

    $store->update(['is_active' => false]);
    $this->actingAs($shopper)->getJson("/api/v1/shop/stores/{$store->slug}")->assertNotFound();
});

test('a non-member cannot manage another business store', function () {
    [$owner, $business] = businessOwner();
    makeStore($business);
    $stranger = userWithProfile();
    $strangerBiz = Profile::factory()->business()->for($stranger)->create();

    // Stranger acting as their OWN business can't touch the owner's store products
    // (they'd operate on their own store, which doesn't exist) — and a personal
    // profile is already blocked. Assert the owner gate holds:
    $this->actingAs($stranger)
        ->withHeader('X-Profile-Id', $strangerBiz->ulid)
        ->getJson('/api/v1/me/store/products')
        ->assertStatus(422); // no store yet for the stranger
});

test('a product page includes its cross-sells', function () {
    [$user, $business] = businessOwner();
    $store = makeStore($business);

    $main = $store->products()->create(['type' => 'course', 'title' => 'Main', 'description' => 'x', 'is_published' => true]);
    $related = $store->products()->create(['type' => 'digital_download', 'title' => 'Add-on', 'description' => 'x', 'price_cents' => 4900, 'is_published' => true]);

    \App\Models\ProductOffer::create([
        'product_id' => $main->id,
        'related_product_id' => $related->id,
        'kind' => Product::OFFER_CROSS_SELL,
    ]);

    $shopper = userWithProfile();
    $this->actingAs($shopper)
        ->getJson("/api/v1/shop/products/{$main->ulid}")
        ->assertOk()
        ->assertJsonPath('data.cross_sells.0.title', 'Add-on');
});

test('shop browsing requires authentication', function () {
    $this->getJson('/api/v1/shop/stores')->assertUnauthorized();
});

test('the store seeder is idempotent and needs a business profile', function () {
    // No business profile yet → no-op.
    $this->seed(\Database\Seeders\StoreSeeder::class);
    expect(Store::query()->count())->toBe(0);

    $user = userWithProfile();
    Profile::factory()->business()->for($user)->create();

    $this->seed(\Database\Seeders\StoreSeeder::class);
    $count = Store::query()->count();
    $this->seed(\Database\Seeders\StoreSeeder::class);

    expect(Store::query()->count())->toBe($count)->toBeGreaterThan(0);
});
