<?php

namespace Database\Seeders;

use App\Models\DailyAction;
use Illuminate\Database\Seeder;

class DailyActionSeeder extends Seeder
{
    public function run(): void
    {
        if (DailyAction::query()->exists()) {
            return;
        }

        $actions = [
            ['Ask one customer for a testimonial', 'A short quote or review builds trust for the next buyer.'],
            ['Update your Google Business Profile', 'Check your hours, photos and contact details are current.'],
            ['Introduce yourself to another member', 'Find someone in your industry and send a friendly hello.'],
            ['Contact one dormant customer', "Reach out to someone who hasn't bought in a while."],
            ['Post one photo of your work', 'Show what you made or did today — people love behind-the-scenes.'],
            ['Reply to one question in the community', 'Help another owner and grow your reputation.'],
            ['Write down one goal for this week', 'A single clear goal keeps the week focused.'],
        ];

        foreach ($actions as [$title, $description]) {
            DailyAction::create([
                'title' => $title,
                'description' => $description,
                'is_active' => true,
            ]);
        }
    }
}
