<?php

use App\Models\Event;
use App\Models\Post;

function createEvent($test, $user, ?string $startsAt = null): string
{
    return $test->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'event',
        'payload' => [
            'title' => 'Launch Party',
            'starts_at' => $startsAt ?? now()->addWeek()->toISOString(),
            'venue' => 'The Rooftop',
        ],
    ])->json('data.ulid');
}

test('an event is created with its details', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'event',
        'payload' => [
            'title' => 'Launch Party',
            'starts_at' => now()->addWeek()->toISOString(),
            'venue' => 'The Rooftop',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'event')
        ->assertJsonPath('data.event.title', 'Launch Party')
        ->assertJsonPath('data.event.venue', 'The Rooftop')
        ->assertJsonPath('data.event.going_count', 0)
        ->assertJsonPath('data.event.viewer_rsvp', null);
});

test('publishing an event in the past is rejected', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'event',
        'payload' => [
            'title' => 'Past Party',
            'starts_at' => now()->subDay()->toISOString(),
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.starts_at');

    expect(Post::count())->toBe(0);
});

test('an event end time must be after the start time', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'event',
        'payload' => [
            'title' => 'Party',
            'starts_at' => now()->addWeek()->toISOString(),
            'ends_at' => now()->addDay()->toISOString(),
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.ends_at');
});

test('rsvping to an event switches status and maintains counters', function () {
    $author = userWithProfile();
    $guest = userWithProfile();
    $ulid = createEvent($this, $author);

    $this->actingAs($guest)->postJson("/api/v1/posts/{$ulid}/rsvp", ['status' => 'going'])
        ->assertOk()
        ->assertJsonPath('data.event.going_count', 1)
        ->assertJsonPath('data.event.interested_count', 0)
        ->assertJsonPath('data.event.viewer_rsvp', 'going');

    // Switch to interested.
    $this->actingAs($guest)->postJson("/api/v1/posts/{$ulid}/rsvp", ['status' => 'interested'])
        ->assertOk()
        ->assertJsonPath('data.event.going_count', 0)
        ->assertJsonPath('data.event.interested_count', 1)
        ->assertJsonPath('data.event.viewer_rsvp', 'interested');

    // Withdraw.
    $this->actingAs($guest)->postJson("/api/v1/posts/{$ulid}/rsvp", ['status' => 'none'])
        ->assertOk()
        ->assertJsonPath('data.event.interested_count', 0)
        ->assertJsonPath('data.event.viewer_rsvp', null);

    expect(Event::first()->going_count)->toBe(0)
        ->and(Event::first()->interested_count)->toBe(0);
});

test('an invalid rsvp status is rejected', function () {
    $author = userWithProfile();
    $guest = userWithProfile();
    $ulid = createEvent($this, $author);

    $this->actingAs($guest)->postJson("/api/v1/posts/{$ulid}/rsvp", ['status' => 'maybe'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
});
