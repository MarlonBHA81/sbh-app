<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BadgeSeeder::class,
            RankSeeder::class,
            XpActionSeeder::class,
            TopicSeeder::class,
            BusinessCategorySeeder::class,
            SettingSeeder::class,
            OpportunitySeeder::class,
            WellnessResourceSeeder::class,
            DailyActionSeeder::class,
            ResourceSeeder::class,
            LessonSeeder::class,
            BriefItemSeeder::class,
            MasterclassSeeder::class,
        ]);
    }
}
