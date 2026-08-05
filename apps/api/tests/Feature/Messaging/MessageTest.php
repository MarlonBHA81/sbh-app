<?php

use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use App\Services\SafetyService;

/**
 * Open a DM and return [conversation, meUser, themUser].
 *
 * @return array{0: Conversation, 1: User, 2: User}
 */
function dmBetween(): array
{
    $me = userWithProfile();
    $them = userWithProfile();

    $ulid = test()->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->json('data.ulid');

    return [Conversation::firstWhere('ulid', $ulid), $me, $them];
}

test('a participant can send a text message and it bumps the conversation', function () {
    [$conversation, $me] = dmBetween();

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hello'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'hello')
        ->assertJsonPath('data.hidden', false)
        ->assertJsonPath('data.deleted', false);

    $conversation->refresh();
    expect($conversation->messages_count)->toBe(1)
        ->and($conversation->last_message_at)->not->toBeNull();
});

test('a message must contain text or media', function () {
    [$conversation, $me] = dmBetween();

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", [])
        ->assertJsonValidationErrors('body');
});

test('a message can attach up to four owned unattached images', function () {
    [$conversation, $me] = dmBetween();

    $ids = Media::factory()->count(3)->create(['profile_id' => $me->personalProfile->id])->pluck('ulid')->all();

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", [
        'media_ids' => $ids,
    ])->assertCreated()
        ->assertJsonCount(3, 'data.media');
});

test('attaching more than four images is rejected', function () {
    [$conversation, $me] = dmBetween();

    $ids = Media::factory()->count(5)->create(['profile_id' => $me->personalProfile->id])->pluck('ulid')->all();

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", [
        'media_ids' => $ids,
    ])->assertJsonValidationErrors('media_ids');
});

test('attaching another profile media is rejected', function () {
    [$conversation, $me, $them] = dmBetween();

    $foreign = Media::factory()->create(['profile_id' => $them->personalProfile->id]);

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", [
        'media_ids' => [$foreign->ulid],
    ])->assertJsonValidationErrors('media_ids');
});

test('non-image media cannot be attached to a message', function () {
    [$conversation, $me] = dmBetween();

    $video = Media::factory()->video()->create(['profile_id' => $me->personalProfile->id]);

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", [
        'media_ids' => [$video->ulid],
    ])->assertJsonValidationErrors('media_ids');
});

test('an image-only message previews as a photo placeholder in the list', function () {
    [$conversation, $me] = dmBetween();
    $image = Media::factory()->create(['profile_id' => $me->personalProfile->id]);

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['media_ids' => [$image->ulid]]);

    $this->actingAs($me)->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.last_message.preview', '📷 Photo');
});

test('you can reply to a message in the same conversation', function () {
    [$conversation, $me] = dmBetween();

    $target = $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'first'])->json('data.ulid');

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", [
        'body' => 'reply', 'reply_to_ulid' => $target,
    ])->assertCreated()
        ->assertJsonPath('data.reply_to.ulid', $target);
});

test('replying across conversations is rejected with 422', function () {
    [$conversationA, $me] = dmBetween();
    $otherTarget = userWithProfile();
    $ulidB = $this->actingAs($me)->postJson('/api/v1/conversations', ['kind' => 'dm', 'profile_ulid' => $otherTarget->personalProfile->ulid])->json('data.ulid');
    $conversationB = Conversation::firstWhere('ulid', $ulidB);

    $foreign = $this->actingAs($me)->postJson("/api/v1/conversations/{$conversationB->ulid}/messages", ['body' => 'in B'])->json('data.ulid');

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversationA->ulid}/messages", [
        'body' => 'bad reply', 'reply_to_ulid' => $foreign,
    ])->assertUnprocessable()->assertJsonValidationErrors('reply_to_ulid');
});

test('a non-participant cannot list or send messages (404)', function () {
    [$conversation] = dmBetween();
    $outsider = userWithProfile();

    $this->actingAs($outsider)->getJson("/api/v1/conversations/{$conversation->ulid}/messages")->assertNotFound();
    $this->actingAs($outsider)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hi'])->assertNotFound();
});

test('a participant who left cannot send messages (403)', function () {
    [$conversation, $me] = dmBetween();

    $this->actingAs($me)->deleteJson("/api/v1/conversations/{$conversation->ulid}/participants/{$me->personalProfile->ulid}");

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hi'])
        ->assertForbidden();
});

test('the conversation itself returns 404 for a non-participant', function () {
    [$conversation] = dmBetween();
    $outsider = userWithProfile();

    $this->actingAs($outsider)->getJson("/api/v1/conversations/{$conversation->ulid}")->assertNotFound();
});

test('deleting your own message tombstones it', function () {
    [$conversation, $me] = dmBetween();
    $ulid = $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'oops'])->json('data.ulid');

    $this->actingAs($me)->deleteJson("/api/v1/messages/{$ulid}")->assertNoContent();

    expect(Message::withTrashed()->firstWhere('ulid', $ulid)->trashed())->toBeTrue();

    $this->actingAs($me)->getJson("/api/v1/conversations/{$conversation->ulid}/messages")
        ->assertOk()
        ->assertJsonPath('data.0.deleted', true)
        ->assertJsonPath('data.0.body', null);
});

test('you cannot delete another profile message', function () {
    [$conversation, $me, $them] = dmBetween();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'mine'])->json('data.ulid');

    $this->actingAs($me)->deleteJson("/api/v1/messages/{$ulid}")->assertForbidden();
});

test('messages from a blocked pair are hidden but keep their slot in a group', function () {
    $viewer = userWithProfile();
    $friend = userWithProfile();
    $foe = userWithProfile();

    // Build the group before the block so the foe is a participant.
    $ulid = $this->actingAs($viewer)->postJson('/api/v1/conversations', [
        'kind' => 'group', 'title' => 'Room',
        'member_ulids' => [$friend->personalProfile->ulid, $foe->personalProfile->ulid],
    ])->json('data.ulid');
    $conversation = Conversation::firstWhere('ulid', $ulid);
    $conversation->update(['approval_status' => Conversation::APPROVAL_APPROVED, 'approved_at' => now()]);

    $this->actingAs($foe)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'secret']);
    $this->actingAs($friend)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'visible']);

    // Viewer blocks the foe after the fact.
    app(SafetyService::class)->block($viewer->personalProfile, $foe->personalProfile);

    $response = $this->actingAs($viewer)->getJson("/api/v1/conversations/{$conversation->ulid}/messages")->assertOk();

    // Newest first: friend's message visible, foe's message hidden but present.
    $response->assertJsonPath('data.0.body', 'visible')
        ->assertJsonPath('data.1.hidden', true)
        ->assertJsonPath('data.1.body', null);

    expect($response->json('data'))->toHaveCount(2);
});
