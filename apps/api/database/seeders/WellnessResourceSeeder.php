<?php

namespace Database\Seeders;

use App\Models\WellnessResource;
use Illuminate\Database\Seeder;

class WellnessResourceSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: don't duplicate on re-seed.
        if (WellnessResource::query()->exists()) {
            return;
        }

        $samples = [
            [
                'category' => 'encouragement',
                'title' => 'Running a business is hard — you\'re not alone',
                'body' => "Every founder you admire has had weeks that felt impossible. Struggling with something doesn't mean you're doing it wrong; it means you're doing something difficult. Whatever this week looks like, you showed up — and that counts.",
                'position' => 1,
                'is_published' => true,
            ],
            [
                'category' => 'reflection',
                'title' => 'One thing that went right',
                'body' => 'Take a moment to name one thing — however small — that went right this week. A customer who came back, an email you finally sent, a quiet afternoon. Progress is rarely loud. Noticing it is a skill worth practising.',
                'position' => 2,
                'is_published' => true,
            ],
            [
                'category' => 'rest',
                'title' => 'Rest is part of the work',
                'body' => "You can't pour from an empty cup. Stepping away from the business for an hour, an evening, or a day isn't falling behind — it's how you stay in it for the long run. Give yourself permission to stop today.",
                'position' => 3,
                'is_published' => true,
            ],
            [
                'category' => 'connection',
                'title' => 'Reach out to one person',
                'body' => "Isolation makes everything feel heavier. Is there one person — a fellow owner, a mentor, a friend — you could message today, just to talk? You don't need a reason. Connection is its own reason.",
                'position' => 4,
                'is_published' => true,
            ],
        ];

        foreach ($samples as $sample) {
            WellnessResource::create($sample);
        }
    }
}
