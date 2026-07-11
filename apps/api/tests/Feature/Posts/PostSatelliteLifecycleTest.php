<?php

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;

test('a draft polls options can be rebuilt on update', function () {
    $user = userWithProfile();

    $ulid = $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'poll',
        'body' => 'Original?',
        'payload' => ['options' => ['a', 'b']],
        'status' => 'draft',
    ])->json('data.ulid');

    $this->actingAs($user)->patchJson("/api/v1/posts/{$ulid}", [
        'payload' => ['options' => ['x', 'y', 'z']],
    ])
        ->assertOk()
        ->assertJsonCount(3, 'data.poll.options')
        ->assertJsonPath('data.poll.options.0.label', 'x');

    // Old options are gone, not orphaned.
    expect(PollOption::count())->toBe(3);
});

test('deleting a post cascades its satellite rows', function () {
    $user = userWithProfile();

    $ulid = $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'poll',
        'body' => 'Q?',
        'payload' => ['options' => ['a', 'b']],
    ])->json('data.ulid');

    expect(Poll::count())->toBe(1)->and(PollOption::count())->toBe(2);

    $this->actingAs($user)->deleteJson("/api/v1/posts/{$ulid}")->assertNoContent();

    expect(Poll::count())->toBe(0)
        ->and(PollOption::count())->toBe(0)
        ->and(Post::withTrashed()->whereNotNull('deleted_at')->count())->toBe(1);
});

test('an event RSVP is scoped to the acting profile', function () {
    $author = userWithProfile();
    $guestA = userWithProfile();
    $guestB = userWithProfile();

    $ulid = $this->actingAs($author)->postJson('/api/v1/posts', [
        'type' => 'event',
        'payload' => ['title' => 'E', 'starts_at' => now()->addWeek()->toISOString()],
    ])->json('data.ulid');

    $this->actingAs($guestA)->postJson("/api/v1/posts/{$ulid}/rsvp", ['status' => 'going']);
    $this->actingAs($guestB)->postJson("/api/v1/posts/{$ulid}/rsvp", ['status' => 'going'])
        ->assertJsonPath('data.event.going_count', 2);

    // Guest B sees their own RSVP; the author (no RSVP) sees null.
    $this->actingAs($author)->getJson("/api/v1/posts/{$ulid}")
        ->assertJsonPath('data.event.viewer_rsvp', null)
        ->assertJsonPath('data.event.going_count', 2);
});
