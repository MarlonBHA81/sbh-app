<?php

use App\Models\Order;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function deliveryFixtures(): array
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
    ]);

    return [$owner, $business, $store, $product];
}

test('a vendor can upload a deliverable file to the private disk', function () {
    Storage::fake('local');
    [$owner, $business, $store, $product] = deliveryFixtures();

    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->post("/api/v1/me/store/products/{$product->ulid}/file", [
            'file' => UploadedFile::fake()->create('templates.zip', 200, 'application/zip'),
        ])
        ->assertOk()
        ->assertJsonPath('data.uploaded', true);

    $path = $product->fresh()->download_path;
    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);
});

test('a buyer who owns a product can download it', function () {
    Storage::fake('local');
    [$owner, $business, $store, $product] = deliveryFixtures();
    $product->update(['download_path' => 'products/'.$product->ulid.'/file.zip']);
    Storage::disk('local')->put($product->download_path, 'the goods');

    $buyer = userWithProfile();
    Purchase::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($buyer)
        ->get("/api/v1/me/purchases/{$product->ulid}/download")
        ->assertOk()
        ->assertDownload();
});

test('a non-buyer cannot download a product', function () {
    Storage::fake('local');
    [$owner, $business, $store, $product] = deliveryFixtures();
    $product->update(['download_path' => 'products/'.$product->ulid.'/file.zip']);
    Storage::disk('local')->put($product->download_path, 'the goods');

    $stranger = userWithProfile();
    $this->actingAs($stranger)
        ->get("/api/v1/me/purchases/{$product->ulid}/download")
        ->assertForbidden();
});

test('a buyer sees their purchases with download availability', function () {
    Storage::fake('local');
    [$owner, $business, $store, $product] = deliveryFixtures();
    $product->update(['download_path' => 'products/'.$product->ulid.'/file.zip']);
    Storage::disk('local')->put($product->download_path, 'the goods');

    $buyer = userWithProfile();
    Purchase::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($buyer)
        ->getJson('/api/v1/me/purchases')
        ->assertOk()
        ->assertJsonPath('data.0.product.title', 'Pack')
        ->assertJsonPath('data.0.has_download', true);
});

test('the vendor sees paid order earnings', function () {
    [$owner, $business, $store, $product] = deliveryFixtures();

    $buyer = userWithProfile();
    $order = Order::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'store_id' => $store->id,
        'status' => Order::STATUS_PENDING,
        'total_cents' => 9900,
        'currency' => 'ZAR',
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'title' => 'Pack', 'unit_cents' => 9900, 'kind' => 'item',
    ]);
    config()->set('payments.platform_fee_percent', 10);
    $order->markPaid();

    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me/store/orders')
        ->assertOk()
        ->assertJsonPath('data.orders_count', 1)
        ->assertJsonPath('data.gross_cents', 9900)
        ->assertJsonPath('data.earnings_cents', 8910);
});

/*
|--------------------------------------------------------------------------
| Unrestricted-upload regressions (audit CB-3 / CB-4)
|--------------------------------------------------------------------------
| Both deliverable endpoints previously validated size only, so a vendor could
| distribute arbitrary executables/scripts to buyers through the store.
*/

test('a vendor cannot upload an executable as a product deliverable', function () {
    Storage::fake('local');
    [$owner, $business, $store, $product] = deliveryFixtures();

    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->post("/api/v1/me/store/products/{$product->ulid}/file", [
            'file' => UploadedFile::fake()->create('payload.exe', 50, 'application/x-msdownload'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect($product->fresh()->download_path)->toBeNull();
});

test('a vendor cannot smuggle a script past the allow-list by renaming it', function () {
    Storage::fake('local');
    [$owner, $business, $store, $product] = deliveryFixtures();

    // .pdf name, but the detected type is a shell script — `mimes:` checks the
    // detected type, so the rename must not help.
    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->post("/api/v1/me/store/products/{$product->ulid}/file", [
            'file' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/x-sh'),
        ])
        ->assertStatus(422);

    expect($product->fresh()->download_path)->toBeNull();
});

test('a vendor cannot upload an executable as a course lesson attachment', function () {
    Storage::fake('local');
    [$owner, $business, $store, $product] = deliveryFixtures();

    $module = $product->modules()->create(['title' => 'M1', 'position' => 1]);
    $lesson = $module->lessons()->create(['title' => 'L1', 'position' => 1]);

    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->post("/api/v1/me/store/lessons/{$lesson->ulid}/attachment", [
            'file' => UploadedFile::fake()->create('payload.exe', 50, 'application/x-msdownload'),
        ])
        ->assertStatus(422);

    expect($lesson->fresh()->attachment_path)->toBeNull();
});
