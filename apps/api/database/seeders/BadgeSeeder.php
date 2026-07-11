<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'key' => 'verified',
                'name' => 'Verified',
                'description' => 'This profile has been verified.',
                'icon' => 'badge-check',
                'kind' => 'verification',
            ],
            [
                'key' => 'professional',
                'name' => 'Professional',
                'description' => 'Recognized professional in their category.',
                'icon' => 'briefcase',
                'kind' => 'category',
            ],
            // Rank badges (kind 'rank') are owned by RankSeeder so they stay in
            // sync with their ranks.
        ];

        foreach ($badges as $badge) {
            Badge::query()->updateOrCreate(['key' => $badge['key']], $badge);
        }
    }
}
