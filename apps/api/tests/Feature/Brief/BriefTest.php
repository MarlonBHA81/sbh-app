<?php

use App\Models\BriefItem;
use App\Models\DailyBrief;
use Database\Seeders\BriefItemSeeder;

function makeBriefItem(array $attributes = []): BriefItem
{
    return BriefItem::create(array_merge([
        'kind' => 'tip',
        'title' => 'A helpful tip',
        'body' => 'Some useful guidance for your business.',
        'is_published' => true,
    ], $attributes));
}

test('the brief returns only published items', function () {
    $user = userWithProfile();

    makeBriefItem(['title' => 'Published tip']);
    makeBriefItem(['title' => 'Draft tip', 'is_published' => false]);

    $this->actingAs($user)
        ->getJson('/api/v1/me/brief')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.title', 'Published tip');
});

test('the brief matches the member industry plus general items', function () {
    $user = userWithProfile(['category' => 'Retail']);

    makeBriefItem(['title' => 'Retail only', 'industry' => 'Retail']);
    makeBriefItem(['title' => 'For everyone', 'industry' => null]);
    makeBriefItem(['title' => 'Services only', 'industry' => 'Services']);

    $titles = collect(
        $this->actingAs($user)
            ->getJson('/api/v1/me/brief')
            ->assertOk()
            ->json('data.items')
    )->pluck('title');

    expect($titles)->toContain('Retail only')
        ->toContain('For everyone')
        ->not->toContain('Services only');
});

test('a member with no industry sees only general items', function () {
    $user = userWithProfile(['category' => null]);

    makeBriefItem(['title' => 'For everyone', 'industry' => null]);
    makeBriefItem(['title' => 'Retail only', 'industry' => 'Retail']);

    $this->actingAs($user)
        ->getJson('/api/v1/me/brief')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.title', 'For everyone');
});

test('the brief caps items at three', function () {
    $user = userWithProfile();

    foreach (range(1, 5) as $i) {
        makeBriefItem(['title' => "Tip {$i}"]);
    }

    $this->actingAs($user)
        ->getJson('/api/v1/me/brief')
        ->assertOk()
        ->assertJsonCount(3, 'data.items');
});

test('the brief returns a non-empty headline and caches it for the day', function () {
    $user = userWithProfile(['category' => 'Retail']);

    $first = $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk();

    expect($first->json('data.headline'))->toBeString()->not->toBe('');
    expect($first->json('data.date'))->toBe(now()->toDateString());
    expect(DailyBrief::query()->count())->toBe(1);

    // A second read reuses the cached headline — no new row.
    $second = $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk();

    expect($second->json('data.headline'))->toBe($first->json('data.headline'));
    expect(DailyBrief::query()->count())->toBe(1);
});

test('brief items expose ulid, never id', function () {
    $user = userWithProfile();
    makeBriefItem();

    $item = $this->actingAs($user)
        ->getJson('/api/v1/me/brief')
        ->assertOk()
        ->json('data.items.0');

    expect($item)->toHaveKeys(['ulid', 'kind', 'title', 'body', 'url', 'published_at'])
        ->not->toHaveKey('id');
});

test('the brief requires authentication', function () {
    $this->getJson('/api/v1/me/brief')->assertUnauthorized();
});

test('the brief item seeder is idempotent', function () {
    $this->seed(BriefItemSeeder::class);
    $count = BriefItem::query()->count();

    $this->seed(BriefItemSeeder::class);

    expect(BriefItem::query()->count())->toBe($count)->toBeGreaterThan(0);
});
