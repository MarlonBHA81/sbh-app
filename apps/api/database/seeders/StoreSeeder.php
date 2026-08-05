<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Profile;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent, and a no-op on a fresh install (no business profile to own a store yet).
        if (Store::query()->exists()) {
            return;
        }

        $profile = Profile::query()->where('kind', Profile::KIND_BUSINESS)->first();

        if ($profile === null) {
            return;
        }

        $store = Store::create([
            'profile_id' => $profile->id,
            'slug' => 'sample-store-'.Str::lower(Str::random(6)),
            'name' => $profile->name.' Store',
            'tagline' => 'Digital products & courses for growing businesses',
            'about' => 'A sample storefront showing how vendors sell on SBH.',
            'brand_color' => '#4e8a88',
            'accent_color' => '#683f59',
            'is_active' => true,
        ]);

        $store->products()->createMany([
            [
                'type' => 'digital_download',
                'title' => 'Cash-flow template pack',
                'description' => 'A ready-to-use spreadsheet pack for tracking money in and out.',
                'price_cents' => 9900,
                'currency' => 'ZAR',
                'is_published' => true,
            ],
            [
                'type' => 'course',
                'title' => 'Pricing your services (mini course)',
                'description' => 'Four short lessons on pricing with confidence and protecting your margin.',
                'price_cents' => 24900,
                'currency' => 'ZAR',
                'is_published' => true,
            ],
        ]);
    }
}
