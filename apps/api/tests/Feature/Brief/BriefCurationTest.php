<?php

use App\Models\BriefItem;
use App\Models\DailyBrief;
use App\Models\Setting;
use App\Services\Ai\AiGateway;
use Tests\Support\FakeAiGateway;

function curationBriefItem(array $attributes = []): BriefItem
{
    return BriefItem::create(array_merge([
        'kind' => 'tip',
        'title' => 'A helpful tip',
        'body' => 'Some useful guidance for your business.',
        'is_published' => true,
    ], $attributes));
}

test('AI curation orders brief items by the AI ranking', function () {
    $user = userWithProfile(['category' => 'Retail']);
    $alpha = curationBriefItem(['title' => 'Alpha']);
    curationBriefItem(['title' => 'Bravo']);
    $charlie = curationBriefItem(['title' => 'Charlie']);

    $fake = new FakeAiGateway(enabled: true);
    $fake->rankedKeys = [$charlie->ulid, $alpha->ulid]; // AI picks C then A, drops B
    app()->instance(AiGateway::class, $fake);

    $titles = collect(
        $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk()->json('data.items')
    )->pluck('title');

    expect($titles->all())->toBe(['Charlie', 'Alpha'])
        ->and($fake->rankCalls)->toBe(1);
});

test('AI curation is cached for the day — no second AI call', function () {
    $user = userWithProfile(['category' => 'Retail']);
    $alpha = curationBriefItem(['title' => 'Alpha']);
    $bravo = curationBriefItem(['title' => 'Bravo']);

    $fake = new FakeAiGateway(enabled: true);
    $fake->rankedKeys = [$bravo->ulid, $alpha->ulid];
    app()->instance(AiGateway::class, $fake);

    $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk();
    $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk();

    expect($fake->rankCalls)->toBe(1)
        ->and(DailyBrief::query()->count())->toBe(1);
});

test('curation falls back to the industry match when AI is disabled', function () {
    $user = userWithProfile(['category' => 'Retail']);
    curationBriefItem(['title' => 'Retail only', 'industry' => 'Retail']);
    curationBriefItem(['title' => 'Services only', 'industry' => 'Services']);
    curationBriefItem(['title' => 'General']);

    // Default null AI driver is disabled → industry match, general included.
    $titles = collect(
        $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk()->json('data.items')
    )->pluck('title');

    expect($titles)->toContain('Retail only')
        ->toContain('General')
        ->not->toContain('Services only');
});

test('the ai_curation flag off forces the industry fallback even with AI on', function () {
    $user = userWithProfile(['category' => 'Retail']);
    $alpha = curationBriefItem(['title' => 'Alpha']);
    curationBriefItem(['title' => 'Bravo']);

    Setting::set('features.ai_curation', false);

    $fake = new FakeAiGateway(enabled: true);
    $fake->rankedKeys = [$alpha->ulid];
    app()->instance(AiGateway::class, $fake);

    $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk();

    // AI never consulted for ranking when the flag is off.
    expect($fake->rankCalls)->toBe(0);
});

test('the daily_brief flag off hides the brief entirely', function () {
    $user = userWithProfile();
    curationBriefItem(['title' => 'A tip']);

    Setting::set('features.daily_brief', false);

    $res = $this->actingAs($user)->getJson('/api/v1/me/brief')->assertOk();

    expect($res->json('data.items'))->toBe([])
        ->and($res->json('data.headline'))->toBe('');
});
