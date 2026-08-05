<?php

use App\Models\Opportunity;

function coachOpp(array $attributes = []): Opportunity
{
    return Opportunity::create(array_merge([
        'type' => 'funding',
        'title' => 'An opportunity',
        'description' => 'Details here.',
        'is_published' => true,
    ], $attributes));
}

test('coach suggestions return fit-ranked opportunities and lessons', function () {
    $user = userWithProfile(['category' => 'Retail', 'journey_stage' => 'growing_sales']);

    coachOpp(['title' => 'Retail grant', 'industry' => 'Retail']);
    coachOpp(['title' => 'General grant', 'industry' => null]);
    coachOpp(['title' => 'Tech grant', 'industry' => 'Technology']);

    $data = $this->actingAs($user)
        ->getJson('/api/v1/coach/suggestions')
        ->assertOk()
        ->json('data');

    // The industry match ranks first and is labelled.
    expect($data['opportunities'][0]['title'])->toBe('Retail grant')
        ->and($data['opportunities'][0]['fit_reason'])->toBe('Matches your industry');

    // Lessons key is present (may be empty without seeded lessons).
    expect($data)->toHaveKey('lessons');
});

test('a closing-soon opportunity is flagged in the fit reason', function () {
    $user = userWithProfile(['category' => null]);

    coachOpp(['title' => 'Soon', 'industry' => null, 'closes_at' => now()->addDays(5)->toDateString()]);

    $data = $this->actingAs($user)
        ->getJson('/api/v1/coach/suggestions')
        ->assertOk()
        ->json('data.opportunities');

    expect(collect($data)->firstWhere('title', 'Soon')['fit_reason'])->toBe('Closing soon');
});

test('the coach drafts a proposal outline from the canned driver', function () {
    $user = userWithProfile();

    $reply = $this->actingAs($user)
        ->postJson('/api/v1/coach/messages', ['body' => 'Help me draft a proposal for a tender'])
        ->assertCreated()
        ->json('data.assistant.body');

    expect($reply)->toContain('proposal')
        ->and(strtolower($reply))->toContain('scope');
});

test('coach suggestions require authentication', function () {
    $this->getJson('/api/v1/coach/suggestions')->assertUnauthorized();
});
