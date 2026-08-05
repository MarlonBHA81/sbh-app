<?php

namespace Database\Seeders;

use App\Models\BriefItem;
use Illuminate\Database\Seeder;

class BriefItemSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: don't duplicate on re-seed.
        if (BriefItem::query()->exists()) {
            return;
        }

        $samples = [
            [
                'kind' => 'tip',
                'title' => 'Follow up within 24 hours',
                'body' => 'Customers who get a quick reply are far more likely to buy. Set aside 15 minutes each morning to answer enquiries and follow up on quotes you sent yesterday.',
                'is_published' => true,
            ],
            [
                'kind' => 'regulation',
                'title' => 'Keep your CIPC details up to date',
                'body' => 'Registered businesses must file an annual return with CIPC to stay in good standing. Check your anniversary month and budget for the small filing fee so you are never deregistered by accident.',
                'is_published' => true,
            ],
            [
                'kind' => 'news',
                'title' => 'More buyers are asking for digital payments',
                'body' => 'Offering a simple way to pay by card or app can win you sales you would otherwise lose. Even a basic tap-to-pay or a payment link shared on WhatsApp makes a difference.',
                'is_published' => true,
            ],
            [
                'kind' => 'resource',
                'title' => 'A one-page cash-flow template',
                'body' => 'Track money in vs money out week by week so you always know what is coming. The same view is what lenders and grant panels want to see. Find templates in the Resource Library.',
                'url' => '/resources',
                'is_published' => true,
            ],
            [
                'kind' => 'tip',
                'title' => 'Photograph your stock in daylight',
                'body' => 'Clear, bright photos sell retail products faster online. Shoot near a window against a plain background — no studio needed — and your listings will look more professional.',
                'industry' => 'Retail',
                'is_published' => true,
            ],
            [
                'kind' => 'tip',
                'title' => 'Confirm bookings the day before',
                'body' => 'A quick confirmation message the day before cuts no-shows for services businesses. It also gives customers an easy moment to reschedule instead of simply not arriving.',
                'industry' => 'Services',
                'is_published' => true,
            ],
        ];

        foreach ($samples as $sample) {
            BriefItem::create($sample);
        }
    }
}
