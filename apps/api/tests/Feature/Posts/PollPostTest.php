<?php

use App\Events\PollVoteTallied;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Support\Facades\Event;

function createPoll($test, $user, array $options = ['Red', 'Green', 'Blue'], ?int $duration = 24): string
{
    return $test->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'poll',
        'body' => 'Favourite colour?',
        'payload' => array_filter(['options' => $options, 'duration_hours' => $duration]),
    ])->json('data.ulid');
}

test('a poll is created with options and an end time', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'poll',
        'body' => 'Favourite colour?',
        'payload' => ['options' => ['Red', 'Green', 'Blue'], 'duration_hours' => 48],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'poll')
        ->assertJsonPath('data.poll.question', 'Favourite colour?')
        ->assertJsonPath('data.poll.total_votes', 0)
        ->assertJsonCount(3, 'data.poll.options')
        ->assertJsonPath('data.poll.options.0.label', 'Red')
        ->assertJsonPath('data.poll.has_ended', false)
        ->assertJsonPath('data.poll.viewer_option_id', null);
});

test('a poll requires between two and six options', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'poll',
        'payload' => ['options' => ['Only one']],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.options');
});

test('voting on a poll updates counters and percentages', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $ulid = createPoll($this, $author);
    $optionId = Poll::first()->options->first()->id;

    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => $optionId])
        ->assertOk()
        ->assertJsonPath('data.poll.total_votes', 1)
        ->assertJsonPath('data.poll.viewer_option_id', $optionId)
        ->assertJsonPath('data.poll.options.0.votes_count', 1)
        ->assertJsonPath('data.poll.options.0.percent', 100);

    expect(PollVote::count())->toBe(1);
});

test('a voter can switch their vote without changing the total', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $ulid = createPoll($this, $author);
    $options = Poll::first()->options;
    $first = $options[0]->id;
    $second = $options[1]->id;

    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => $first]);
    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => $second])
        ->assertOk()
        ->assertJsonPath('data.poll.total_votes', 1)
        ->assertJsonPath('data.poll.viewer_option_id', $second)
        ->assertJsonPath('data.poll.options.0.votes_count', 0)
        ->assertJsonPath('data.poll.options.1.votes_count', 1);

    expect(PollVote::count())->toBe(1);
});

test('re-voting the same option is idempotent', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $ulid = createPoll($this, $author);
    $optionId = Poll::first()->options->first()->id;

    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => $optionId]);
    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => $optionId])
        ->assertOk()
        ->assertJsonPath('data.poll.total_votes', 1);

    expect(PollVote::count())->toBe(1);
});

test('voting after a poll ends is rejected', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $ulid = createPoll($this, $author, duration: 1);
    $optionId = Poll::first()->options->first()->id;

    $this->travel(2)->hours();

    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => $optionId])
        ->assertUnprocessable()->assertJsonValidationErrors('option_id');
});

test('voting for another polls option is rejected', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $ulid = createPoll($this, $author);

    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => 99999])
        ->assertUnprocessable()->assertJsonValidationErrors('option_id');
});

test('a poll vote broadcasts a tally event', function () {
    Event::fake([PollVoteTallied::class]);

    $author = userWithProfile();
    $voter = userWithProfile();
    $ulid = createPoll($this, $author);
    $optionId = Poll::first()->options->first()->id;

    $this->actingAs($voter)->postJson("/api/v1/posts/{$ulid}/poll-vote", ['option_id' => $optionId]);

    Event::assertDispatched(PollVoteTallied::class);
});
