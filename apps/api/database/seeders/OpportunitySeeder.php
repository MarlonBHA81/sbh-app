<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: don't duplicate on re-seed.
        if (Opportunity::query()->exists()) {
            return;
        }

        $samples = [
            [
                'type' => 'funding',
                'title' => 'SEDA Small Enterprise Growth Fund',
                'description' => 'Grants of up to R50,000 for registered small businesses to fund equipment, stock or marketing. Applications reviewed monthly.',
                'organisation' => 'SEDA',
                'url' => 'https://www.seda.org.za',
                'province' => 'National',
                'amount' => 'Up to R50,000',
                'closes_at' => now()->addMonths(2)->toDateString(),
                'is_published' => true,
            ],
            [
                'type' => 'tender',
                'title' => 'Municipal cleaning services tender',
                'description' => 'The metro invites quotations from registered small businesses for office cleaning services across three sites. CSD registration required.',
                'organisation' => 'City Metro',
                'province' => 'Gauteng',
                'industry' => 'Services',
                'amount' => 'R120,000 / year',
                'closes_at' => now()->addWeeks(3)->toDateString(),
                'is_published' => true,
            ],
            [
                'type' => 'programme',
                'title' => 'Retail accelerator — 8-week cohort',
                'description' => 'A free 8-week programme for retail founders covering pricing, stock and digital marketing, with a mentor matched to your business.',
                'organisation' => 'Story Advantage',
                'industry' => 'Retail',
                'province' => 'National',
                'closes_at' => now()->addWeeks(2)->toDateString(),
                'is_published' => true,
            ],
            [
                'type' => 'grant',
                'title' => 'Women in business micro-grant',
                'description' => 'Micro-grants of R10,000 for women-led businesses under two years old. Quick application, no repayment.',
                'organisation' => 'Enterprise Fund',
                'amount' => 'R10,000',
                'is_published' => true,
            ],
        ];

        foreach ($samples as $sample) {
            Opportunity::create($sample);
        }
    }
}
