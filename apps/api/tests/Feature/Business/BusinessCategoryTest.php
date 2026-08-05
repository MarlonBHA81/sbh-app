<?php

use App\Models\BusinessCategory;
use Illuminate\Support\Facades\Cache;

test('categories endpoint returns ordered public list', function () {
    BusinessCategory::factory()->create(['name' => 'Zeta', 'slug' => 'zeta', 'position' => 5, 'icon' => '🅰️']);
    BusinessCategory::factory()->create(['name' => 'Alpha', 'slug' => 'alpha', 'position' => 1]);
    BusinessCategory::factory()->create(['name' => 'Mid', 'slug' => 'mid', 'position' => 3]);

    $response = $this->getJson('/api/v1/business/categories')->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug');

    expect($slugs->toArray())->toBe(['alpha', 'mid', 'zeta']);
    $response->assertJsonStructure(['data' => [['id', 'slug', 'name', 'icon']]]);
});

test('categories endpoint is cached for an hour', function () {
    BusinessCategory::factory()->create(['name' => 'First', 'slug' => 'first', 'position' => 1]);

    $this->getJson('/api/v1/business/categories')->assertOk()->assertJsonCount(1, 'data');

    // A new category inserted after the first (cached) request must not appear.
    BusinessCategory::factory()->create(['name' => 'Second', 'slug' => 'second', 'position' => 2]);

    $this->getJson('/api/v1/business/categories')->assertOk()->assertJsonCount(1, 'data');

    Cache::forget('business:categories');

    $this->getJson('/api/v1/business/categories')->assertOk()->assertJsonCount(2, 'data');
});
