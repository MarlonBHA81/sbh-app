<?php

namespace Database\Seeders;

use App\Models\Masterclass;
use Illuminate\Database\Seeder;

class MasterclassSeeder extends Seeder
{
    public function run(): void
    {
        if (Masterclass::query()->exists()) {
            return;
        }

        $samples = [
            [
                'title' => 'Retail Growth Accelerator (6-week cohort)',
                'description' => 'A six-week programme for retail founders covering pricing, stock control, visual merchandising and digital marketing, with a facilitator and a peer cohort.',
                'facilitator_name' => 'Story Advantage',
                'starts_at' => now()->addWeek()->setTime(18, 0),
                'ends_at' => now()->addWeeks(7)->setTime(20, 0),
                'capacity' => 25,
                'is_published' => true,
            ],
            [
                'title' => 'Cash Flow Mastery (4-week cohort)',
                'description' => 'Four practical weeks on managing money in vs money out, forecasting, and getting funder-ready — with weekly live sessions and templates.',
                'facilitator_name' => 'SBH Coaches',
                'starts_at' => now()->addWeeks(2)->setTime(17, 30),
                'ends_at' => now()->addWeeks(6)->setTime(19, 0),
                'capacity' => null,
                'is_published' => true,
            ],
        ];

        foreach ($samples as $sample) {
            Masterclass::create($sample);
        }
    }
}
