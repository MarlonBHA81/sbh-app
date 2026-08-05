<?php

use App\Http\Controllers\Api\V1\TopicController;
use App\Models\Topic;
use Database\Seeders\TopicSeeder;
use Illuminate\Support\Facades\Cache;

test('the topic seeder is idempotent and builds materialized paths', function () {
    $this->seed(TopicSeeder::class);
    $count = Topic::count();

    $this->seed(TopicSeeder::class);

    expect(Topic::count())->toBe($count)
        ->and($count)->toBeGreaterThanOrEqual(40);

    $ai = Topic::where('slug', 'ai')->firstOrFail();

    expect($ai->path)->toBe('technology/ai')
        ->and($ai->depth)->toBe(1)
        ->and($ai->parent->slug)->toBe('technology');
});

test('topics tree returns the nested tree without auth', function () {
    $this->seed(TopicSeeder::class);

    $response = $this->getJson('/api/v1/topics/tree')->assertOk();

    $tree = collect($response->json('data'));

    expect($tree)->toHaveCount(10);

    $tech = $tree->firstWhere('slug', 'technology');

    expect($tech)->toHaveKeys(['id', 'slug', 'name', 'icon', 'followers_count', 'children'])
        ->and($tech)->not->toHaveKey('is_following')
        ->and(collect($tech['children'])->pluck('slug'))->toContain('ai', 'web-dev', 'gadgets', 'startups');
});

test('topics tree is cached for ten minutes', function () {
    Topic::factory()->create(['slug' => 'first']);

    $this->getJson('/api/v1/topics/tree')->assertOk()->assertJsonCount(1, 'data');

    Topic::factory()->create(['slug' => 'second']);

    // Still served from cache.
    $this->getJson('/api/v1/topics/tree')->assertOk()->assertJsonCount(1, 'data');

    Cache::forget(TopicController::TREE_CACHE_KEY);

    $this->getJson('/api/v1/topics/tree')->assertOk()->assertJsonCount(2, 'data');
});

test('topics tree includes is_following for authenticated viewers', function () {
    $viewer = userWithProfile();
    $topic = Topic::factory()->create(['slug' => 'business']);
    Topic::factory()->create(['slug' => 'food']);

    $this->actingAs($viewer)->postJson('/api/v1/topics/business/follow')->assertCreated();

    $tree = collect($this->actingAs($viewer)->getJson('/api/v1/topics/tree')->assertOk()->json('data'));

    expect($tree->firstWhere('slug', 'business')['is_following'])->toBeTrue()
        ->and($tree->firstWhere('slug', 'food')['is_following'])->toBeFalse();
});

test('topic show returns the topic with children and is_following', function () {
    $viewer = userWithProfile();

    $parent = Topic::factory()->create(['slug' => 'health', 'name' => 'Health']);
    Topic::factory()->childOf($parent)->create(['slug' => 'fitness', 'name' => 'Fitness']);

    $this->getJson('/api/v1/topics/health')
        ->assertOk()
        ->assertJsonPath('data.slug', 'health')
        ->assertJsonPath('data.children.0.slug', 'fitness')
        ->assertJsonMissingPath('data.is_following');

    $this->actingAs($viewer)
        ->getJson('/api/v1/topics/health')
        ->assertOk()
        ->assertJsonPath('data.is_following', false);

    $this->actingAs($viewer)->postJson('/api/v1/topics/health/follow');

    $this->actingAs($viewer)
        ->getJson('/api/v1/topics/health')
        ->assertJsonPath('data.is_following', true);

    $this->getJson('/api/v1/topics/nope')->assertNotFound();
});

test('following and unfollowing a topic maintains followers_count', function () {
    $viewer = userWithProfile();
    $topic = Topic::factory()->create(['slug' => 'finance']);

    $this->actingAs($viewer)
        ->postJson('/api/v1/topics/finance/follow')
        ->assertCreated()
        ->assertJsonPath('data.is_following', true)
        ->assertJsonPath('data.followers_count', 1);

    // Following again is idempotent.
    $this->actingAs($viewer)->postJson('/api/v1/topics/finance/follow')->assertCreated();
    expect($topic->refresh()->followers_count)->toBe(1);

    $this->actingAs($viewer)->deleteJson('/api/v1/topics/finance/follow')->assertNoContent();
    expect($topic->refresh()->followers_count)->toBe(0);

    // Unfollowing again never drives the counter negative.
    $this->actingAs($viewer)->deleteJson('/api/v1/topics/finance/follow')->assertNoContent();
    expect($topic->refresh()->followers_count)->toBe(0);
});

test('me/topics lists the topics the active profile follows', function () {
    $viewer = userWithProfile();

    Topic::factory()->create(['slug' => 'art']);
    Topic::factory()->create(['slug' => 'news']);
    Topic::factory()->create(['slug' => 'gaming']);

    $this->actingAs($viewer)->postJson('/api/v1/topics/news/follow');
    $this->actingAs($viewer)->postJson('/api/v1/topics/art/follow');

    $response = $this->actingAs($viewer)->getJson('/api/v1/me/topics')->assertOk();

    expect(collect($response->json('data'))->pluck('slug')->all())->toBe(['news', 'art'])
        ->and($response->json('data.0.is_following'))->toBeTrue();
});

test('topic follow endpoints require authentication', function () {
    Topic::factory()->create(['slug' => 'music']);

    $this->postJson('/api/v1/topics/music/follow')->assertUnauthorized();
    $this->deleteJson('/api/v1/topics/music/follow')->assertUnauthorized();
    $this->getJson('/api/v1/me/topics')->assertUnauthorized();
});
