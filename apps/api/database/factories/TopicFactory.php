<?php

namespace Database\Factories;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'slug' => Str::slug($name),
            'name' => $name,
            'icon' => '📌',
        ];
    }

    public function childOf(Topic $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
