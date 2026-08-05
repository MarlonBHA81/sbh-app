<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\LessonTrack;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: don't duplicate on re-seed.
        if (LessonTrack::query()->exists() || Lesson::query()->exists()) {
            return;
        }

        $foundations = LessonTrack::create([
            'title' => 'Business foundations',
            'description' => 'The essentials every new business owner needs first.',
            'position' => 1,
            'is_published' => true,
        ]);

        $customers = LessonTrack::create([
            'title' => 'Finding your first customers',
            'description' => 'Practical ways to win early, loyal customers.',
            'position' => 2,
            'is_published' => true,
        ]);

        $lessons = [
            [
                'track' => $foundations,
                'title' => 'Why register your business',
                'body' => "Registering makes your business a real, separate entity. It lets you open a business bank account, sign contracts in the business name, and apply for tenders and funding that require formal registration.\n\nAction: write down the one thing registering would unlock for you this month.",
                'minutes' => 4,
                'journey_stage' => 'starting',
                'position' => 1,
            ],
            [
                'track' => $foundations,
                'title' => 'Separating business and personal money',
                'body' => "Mixing personal and business money hides whether you're actually making a profit. Open a dedicated account and pay yourself a set amount instead of dipping in.\n\nAction: decide on a fixed weekly amount to pay yourself.",
                'minutes' => 5,
                'journey_stage' => 'starting',
                'position' => 2,
            ],
            [
                'track' => $foundations,
                'title' => 'Pricing so you actually profit',
                'body' => "Price = cost + time + margin. Many owners under-price because they forget their own time. List every cost per unit, add a fair hourly rate for your effort, then add a margin.\n\nAction: recalculate the price of your best-selling item.",
                'minutes' => 6,
                'journey_stage' => 'growing_sales',
                'position' => 3,
            ],
            [
                'track' => $customers,
                'title' => 'Your first 10 customers',
                'body' => "Your first customers usually come from people who already trust you. Make a list of 20 people who know you and tell each one, personally, exactly what you offer and who it helps.\n\nAction: send five of those messages today.",
                'minutes' => 5,
                'journey_stage' => 'finding_customers',
                'position' => 1,
            ],
            [
                'track' => $customers,
                'title' => 'Asking for referrals',
                'body' => "A happy customer is your best salesperson. Right after you deliver, ask: 'Who else do you know who needs this?' Make it easy by giving them a short message they can forward.\n\nAction: write one referral message you can reuse.",
                'minutes' => 4,
                'journey_stage' => 'finding_customers',
                'position' => 2,
            ],
        ];

        foreach ($lessons as $data) {
            $track = $data['track'];
            unset($data['track']);

            $track->lessons()->create($data + ['is_published' => true]);
        }
    }
}
