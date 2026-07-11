<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Retail', '🛍️'],
            ['Food & Beverage', '🍽️'],
            ['Professional Services', '💼'],
            ['Construction & Trades', '🏗️'],
            ['Beauty & Wellness', '💇'],
            ['Tech & IT', '💻'],
            ['Marketing & Media', '📣'],
            ['Transport & Logistics', '🚚'],
            ['Education & Training', '🎓'],
            ['Finance & Insurance', '💰'],
            ['Real Estate', '🏠'],
            ['Agriculture', '🌾'],
            ['Manufacturing', '🏭'],
            ['Tourism & Hospitality', '🏨'],
            ['Health & Medical', '⚕️'],
            ['Automotive', '🚗'],
            ['Arts & Crafts', '🎨'],
            ['Events & Entertainment', '🎉'],
            ['Cleaning & Home', '🧹'],
            ['Legal', '⚖️'],
        ];

        foreach ($categories as $position => [$name, $icon]) {
            BusinessCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'position' => $position],
            );
        }
    }
}
