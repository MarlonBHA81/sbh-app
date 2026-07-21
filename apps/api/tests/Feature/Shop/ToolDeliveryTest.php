<?php

use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function toolStore(): Store
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();

    return Store::create([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'Tools', 'is_active' => true,
    ]);
}

test('an HTML deliverable is flagged as an in-app tool', function () {
    $store = toolStore();
    $tool = $store->products()->create([
        'type' => 'digital_download', 'title' => 'Margin calculator', 'description' => 'x',
        'price_cents' => 4900, 'is_published' => true,
        'download_path' => 'products/x/calculator.html',
    ]);

    $shopper = userWithProfile();
    $this->actingAs($shopper)
        ->getJson("/api/v1/shop/products/{$tool->ulid}")
        ->assertOk()
        ->assertJsonPath('data.is_html_tool', true)
        ->assertJsonPath('data.type', 'digital_download');
});

test('a zip deliverable is not flagged as a tool', function () {
    $store = toolStore();
    $product = $store->products()->create([
        'type' => 'digital_download', 'title' => 'Pack', 'description' => 'x',
        'price_cents' => 4900, 'is_published' => true,
        'download_path' => 'products/x/pack.zip',
    ]);

    $shopper = userWithProfile();
    $this->actingAs($shopper)
        ->getJson("/api/v1/shop/products/{$product->ulid}")
        ->assertOk()
        ->assertJsonPath('data.is_html_tool', false);
});

test('the external tool url is exposed on the product', function () {
    $store = toolStore();
    $tool = $store->products()->create([
        'type' => 'service', 'title' => 'Hosted calculator', 'description' => 'x',
        'price_cents' => 4900, 'is_published' => true,
        'external_url' => 'https://tools.example.test/calc',
    ]);

    $shopper = userWithProfile();
    $this->actingAs($shopper)
        ->getJson("/api/v1/shop/products/{$tool->ulid}")
        ->assertOk()
        ->assertJsonPath('data.external_url', 'https://tools.example.test/calc');
});

test('only an owner can fetch the gated HTML tool content', function () {
    Storage::fake('local');
    $store = toolStore();
    $tool = $store->products()->create([
        'type' => 'digital_download', 'title' => 'Calc', 'description' => 'x',
        'price_cents' => 4900, 'is_published' => true,
        'download_path' => 'products/tool/calc.html',
    ]);
    Storage::disk('local')->put($tool->download_path, '<html><body>calc</body></html>');

    $buyer = userWithProfile();
    Purchase::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'product_id' => $tool->id,
    ]);

    $this->actingAs($buyer)
        ->get("/api/v1/me/purchases/{$tool->ulid}/download")
        ->assertOk();

    $stranger = userWithProfile();
    $this->actingAs($stranger)
        ->get("/api/v1/me/purchases/{$tool->ulid}/download")
        ->assertForbidden();
});
