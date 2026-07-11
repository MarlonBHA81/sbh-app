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
            [
                'key' => 'rank_newbie',
                'name' => 'Newbie',
                'description' => 'Just getting started.',
                'icon' => 'sprout',
                'kind' => 'rank',
            ],
            [
                'key' => 'rank_expert',
                'name' => 'Expert',
                'description' => 'A seasoned member of the community.',
                'icon' => 'star',
                'kind' => 'rank',
            ],
            [
                'key' => 'rank_legend',
                'name' => 'Legend',
                'description' => 'A legendary member of the community.',
                'icon' => 'crown',
                'kind' => 'rank',
            ],
        ];

        foreach ($badges as $badge) {
            Badge::query()->updateOrCreate(['key' => $badge['key']], $badge);
        }
    }
}
