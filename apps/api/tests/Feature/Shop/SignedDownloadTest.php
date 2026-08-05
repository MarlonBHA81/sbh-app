<?php

use App\Models\Product;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Profile, 2: Product} buyer, buyerProfile, product
 */
function signedFixtures(): array
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();
    $store = Store::create([
        'profile_id' => $business->id,
        'slug' => 'store-'.Str::lower(Str::random(6)),
        'name' => 'S', 'is_active' => true,
    ]);
    $product = $store->products()->create([
        'type' => 'digital_download', 'title' => 'Pack', 'description' => 'x',
        'price_cents' => 9900, 'is_published' => true,
        'download_path' => 'products/file.zip',
    ]);
    Storage::disk('local')->put($product->download_path, 'the goods');

    $buyer = userWithProfile();
    $buyerProfile = $buyer->profiles()->first();
    Purchase::create(['buyer_profile_id' => $buyerProfile->id, 'product_id' => $product->id]);

    return [$buyer, $buyerProfile, $product];
}

function relative(string $url): string
{
    $parts = parse_url($url);

    return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
}

test('an owner is issued a short-lived signed download URL', function () {
    Storage::fake('local');
    [$buyer, , $product] = signedFixtures();

    $res = $this->actingAs($buyer)
        ->getJson("/api/v1/me/purchases/{$product->ulid}/download-url")
        ->assertOk()
        ->assertJsonPath('data.expires_in', 300);

    expect($res->json('data.url'))->toContain('/shop/download/')->toContain('signature=');
});

test('a non-owner cannot mint a download URL', function () {
    Storage::fake('local');
    [, , $product] = signedFixtures();
    $stranger = userWithProfile();

    $this->actingAs($stranger)
        ->getJson("/api/v1/me/purchases/{$product->ulid}/download-url")
        ->assertForbidden();
});

test('a valid signed URL streams the file without a session', function () {
    Storage::fake('local');
    [$buyer, , $product] = signedFixtures();

    $url = $this->actingAs($buyer)
        ->getJson("/api/v1/me/purchases/{$product->ulid}/download-url")
        ->json('data.url');

    // Fresh (unauthenticated) request — the signature is the authorisation.
    $this->get(relative($url))
        ->assertOk()
        ->assertDownload();
});

test('a tampered signature is rejected', function () {
    Storage::fake('local');
    [$buyer, , $product] = signedFixtures();

    $url = $this->actingAs($buyer)
        ->getJson("/api/v1/me/purchases/{$product->ulid}/download-url")
        ->json('data.url');

    $this->get(relative($url).'tampered')->assertForbidden();
});

test('an expired signed URL is rejected', function () {
    Storage::fake('local');
    [$buyer, , $product] = signedFixtures();

    $url = $this->actingAs($buyer)
        ->getJson("/api/v1/me/purchases/{$product->ulid}/download-url")
        ->json('data.url');

    $this->travel(6)->minutes();

    $this->get(relative($url))->assertForbidden();
});
