<?php

use App\Models\Opportunity;

function suggestOpp(array $attributes = []): Opportunity
{
    return Opportunity::create(array_merge([
        'type' => 'funding',
        'title' => 'An opportunity',
        'description' => 'Details.',
        'is_published' => true,
    ], $attributes));
}

test('suggested opportunities are fit-ranked to the member industry', function () {
    $user = userWithProfile(['category' => 'Retail']);

    suggestOpp(['title' => 'General', 'industry' => null]);
    suggestOpp(['title' => 'Retail match', 'industry' => 'Retail']);

    $this->actingAs($user)
        ->getJson('/api/v1/me/opportunities/suggested')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Retail match')
        ->assertJsonPath('data.0.fit_reason', 'Matches your industry')
        ->assertJsonPath('data.0.is_saved', false);
});

test('suggested opportunities exclude drafts and closed listings', function () {
    $user = userWithProfile();

    suggestOpp(['title' => 'Open', 'industry' => null]);
    suggestOpp(['title' => 'Draft', 'is_published' => false]);
    suggestOpp(['title' => 'Closed', 'closes_at' => now()->subDay()->toDateString()]);

    $titles = collect(
        $this->actingAs($user)->getJson('/api/v1/me/opportunities/suggested')->assertOk()->json('data')
    )->pluck('title');

    expect($titles)->toContain('Open')->not->toContain('Draft')->not->toContain('Closed');
});

test('suggested opportunities require authentication', function () {
    $this->getJson('/api/v1/me/opportunities/suggested')->assertUnauthorized();
});
