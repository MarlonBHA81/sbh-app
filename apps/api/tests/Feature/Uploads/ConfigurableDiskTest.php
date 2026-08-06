<?php

use App\Models\Media;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * The media storage disks are configurable (config/media.php), so setting
 * MEDIA_PUBLIC_DISK / MEDIA_PRIVATE_DISK swaps object storage in without any
 * code changes. These tests point the config at an alternate faked disk and
 * assert writes/reads follow it rather than the historical 'public' / 'local'.
 */

test('an uploaded image is stored on the configured public disk', function () {
    config(['media.public_disk' => 's3']);
    Storage::fake('s3');
    // Prove we are not silently falling back to the old default disk.
    Storage::fake('public');

    $user = userWithProfile();

    $this->actingAs($user)
        ->post('/api/v1/media', ['file' => UploadedFile::fake()->image('photo.jpg', 1200, 800)], ['Accept' => 'application/json'])
        ->assertCreated();

    $media = Media::first();

    expect($media->disk)->toBe('s3');

    Storage::disk('s3')->assertExists($media->path);
    Storage::disk('s3')->assertExists($media->thumb_path);
    Storage::disk('public')->assertMissing($media->path);
});

test('a vendor deliverable is written to and served from the configured private disk', function () {
    config(['media.private_disk' => 's3']);
    Storage::fake('s3');
    Storage::fake('local');

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

    // Upload lands on the configured private disk, not the old 'local' default.
    $this->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->post("/api/v1/me/store/products/{$product->ulid}/file", [
            'file' => UploadedFile::fake()->create('templates.zip', 200, 'application/zip'),
        ])
        ->assertOk();

    $path = $product->fresh()->download_path;
    Storage::disk('s3')->assertExists($path);
    Storage::disk('local')->assertMissing($path);

    // And a buyer who owns it downloads from that same configured disk.
    $buyer = userWithProfile();
    Purchase::create([
        'buyer_profile_id' => $buyer->profiles()->first()->id,
        'product_id' => $product->id,
    ]);

    // Override the X-Profile-Id header set on the owner's upload above, which
    // otherwise persists across requests in the same test.
    $this->actingAs($buyer)
        ->withHeader('X-Profile-Id', $buyer->personalProfile->ulid)
        ->get("/api/v1/me/purchases/{$product->ulid}/download")
        ->assertOk()
        ->assertDownload();
});
