<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;

/**
 * @return array{0: Conversation, 1: User, 2: User}
 */
function dmWith(): array
{
    $me = userWithProfile();
    $them = userWithProfile();
    $ulid = test()->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->json('data.ulid');

    return [Conversation::firstWhere('ulid', $ulid), $me, $them];
}

test('a participant can react to a message and it appears in the summary', function () {
    [$conversation, $me, $them] = dmWith();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hey'])->json('data.ulid');

    $this->actingAs($me)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '👍'])->assertNoContent();
    $this->actingAs($them)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '👍'])->assertNoContent();

    $response = $this->actingAs($me)->getJson("/api/v1/conversations/{$conversation->ulid}/messages")->assertOk();

    $reactions = collect($response->json('data.0.reactions'));
    $thumbs = $reactions->firstWhere('emoji', '👍');

    expect($thumbs['count'])->toBe(2)
        ->and($thumbs['reacted_by_me'])->toBeTrue();
});

test('reactions are unique per profile and emoji', function () {
    [$conversation, $me, $them] = dmWith();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hey'])->json('data.ulid');

    $this->actingAs($me)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '🔥']);
    $this->actingAs($me)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '🔥']);

    expect(MessageReaction::count())->toBe(1);
});

test('a reaction can be removed', function () {
    [$conversation, $me, $them] = dmWith();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hey'])->json('data.ulid');

    $this->actingAs($me)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '🔥']);
    $this->actingAs($me)->deleteJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '🔥'])->assertNoContent();

    expect(MessageReaction::count())->toBe(0);
});

test('a non-participant cannot react', function () {
    [$conversation, $me, $them] = dmWith();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hey'])->json('data.ulid');
    $outsider = userWithProfile();

    $this->actingAs($outsider)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '👍'])->assertNotFound();
});

test('an over-long emoji is rejected', function () {
    [$conversation, $me, $them] = dmWith();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hey'])->json('data.ulid');

    $this->actingAs($me)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => str_repeat('a', 17)])
        ->assertJsonValidationErrors('emoji');
});

test('the read cursor only moves forward', function () {
    [$conversation, $me, $them] = dmWith();

    $m1 = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'one'])->json('data.ulid');
    $m2 = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'two'])->json('data.ulid');

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/read", ['message_ulid' => $m2])->assertNoContent();

    // Attempting to go back to an earlier message does not regress the cursor.
    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/read", ['message_ulid' => $m1])->assertNoContent();

    $participant = $conversation->participants()->where('profile_id', $me->personalProfile->id)->first();
    $m2Id = Message::firstWhere('ulid', $m2)->id;

    expect($participant->last_read_message_id)->toBe($m2Id);
});

test('unread_count reflects unread messages not authored by me', function () {
    [$conversation, $me, $them] = dmWith();

    $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'a']);
    $last = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'b'])->json('data.ulid');

    // My own message never counts as unread.
    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'mine']);

    $this->actingAs($me)->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.unread_count', 2);

    // Reading up to the last of their messages clears those two.
    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/read", ['message_ulid' => $last]);

    $this->actingAs($me)->getJson('/api/v1/conversations')
        ->assertJsonPath('data.0.unread_count', 0);
});

test('the unread-messages-count aggregates across conversations', function () {
    $me = userWithProfile();
    $a = userWithProfile();
    $b = userWithProfile();

    $convA = $this->actingAs($me)->postJson('/api/v1/conversations', ['kind' => 'dm', 'profile_ulid' => $a->personalProfile->ulid])->json('data.ulid');
    $convB = $this->actingAs($me)->postJson('/api/v1/conversations', ['kind' => 'dm', 'profile_ulid' => $b->personalProfile->ulid])->json('data.ulid');

    $this->actingAs($a)->postJson("/api/v1/conversations/{$convA}/messages", ['body' => 'x']);
    $this->actingAs($a)->postJson("/api/v1/conversations/{$convA}/messages", ['body' => 'y']);
    $this->actingAs($b)->postJson("/api/v1/conversations/{$convB}/messages", ['body' => 'z']);

    $this->actingAs($me)->getJson('/api/v1/me/unread-messages-count')
        ->assertOk()
        ->assertJsonPath('count', 3);
});
