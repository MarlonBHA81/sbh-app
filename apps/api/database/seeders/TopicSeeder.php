<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TopicSeeder extends Seeder
{
    /**
     * Root topics with their children. Keys are display names; a slug is
     * derived automatically. Safe to re-run (updateOrCreate by slug).
     *
     * @var array<string, array{icon: string, children: list<string>}>
     */
    private const TOPICS = [
        'Business' => ['icon' => '💼', 'children' => ['Small Business', 'Entrepreneurship', 'Networking', 'Retail']],
        'Technology' => ['icon' => '💻', 'children' => ['AI', 'Web Dev', 'Gadgets', 'Startups', 'Cybersecurity']],
        'Marketing' => ['icon' => '📣', 'children' => ['Social Media', 'Branding', 'Advertising', 'SEO']],
        'Finance' => ['icon' => '💰', 'children' => ['Investing', 'Personal Finance', 'Crypto', 'Taxes']],
        'News' => ['icon' => '📰', 'children' => ['Local News', 'World News', 'Politics', 'Sports']],
        'Art & Design' => ['icon' => '🎨', 'children' => ['Graphic Design', 'Photography', 'Illustration', 'Architecture']],
        'Food' => ['icon' => '🍔', 'children' => ['Recipes', 'Restaurants', 'Baking', 'Vegan']],
        'Travel' => ['icon' => '✈️', 'children' => ['Destinations', 'Budget Travel', 'Adventure', 'City Guides']],
        'Health' => ['icon' => '🩺', 'children' => ['Fitness', 'Mental Health', 'Nutrition', 'Wellness']],
        'Entertainment' => ['icon' => '🎬', 'children' => ['Movies', 'Music', 'Gaming', 'Comedy', 'Celebrities']],
    ];

    public function run(): void
    {
        foreach (self::TOPICS as $rootName => $config) {
            $rootSlug = Str::slug($rootName);

            $root = Topic::query()->updateOrCreate(
                ['slug' => $rootSlug],
                [
                    'parent_id' => null,
                    'name' => $rootName,
                    'icon' => $config['icon'],
                    'path' => $rootSlug,
                    'depth' => 0,
                ],
            );

            foreach ($config['children'] as $childName) {
                $childSlug = Str::slug($childName);

                Topic::query()->updateOrCreate(
                    ['slug' => $childSlug],
                    [
                        'parent_id' => $root->id,
                        'name' => $childName,
                        'icon' => null,
                        'path' => $rootSlug.'/'.$childSlug,
                        'depth' => 1,
                    ],
                );
            }
        }
    }
}
