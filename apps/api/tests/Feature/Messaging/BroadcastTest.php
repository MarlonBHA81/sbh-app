<?php

use App\Events\ConversationBumped;
use App\Events\MessageDeleted;
use App\Events\MessageReacted;
use App\Events\MessageSent;
use App\Events\ReadReceipt;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * @return array{0: Conversation, 1: User, 2: User}
 */
function dmForBroadcast(): array
{
    $me = userWithProfile();
    $them = userWithProfile();
    $ulid = test()->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->json('data.ulid');

    return [Conversation::firstWhere('ulid', $ulid), $me, $them];
}

test('sending a message broadcasts MessageSent and ConversationBumped', function () {
    [$conversation, $me] = dmForBroadcast();
    Event::fake([MessageSent::class, ConversationBumped::class]);

    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hi'])->assertCreated();

    Event::assertDispatched(MessageSent::class);
    Event::assertDispatched(ConversationBumped::class, function (ConversationBumped $e) use ($conversation) {
        return $e->conversation->is($conversation) && count($e->recipientProfileUlids) === 1;
    });
});

test('deleting a message broadcasts MessageDeleted', function () {
    [$conversation, $me] = dmForBroadcast();
    $ulid = $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hi'])->json('data.ulid');

    Event::fake([MessageDeleted::class]);
    $this->actingAs($me)->deleteJson("/api/v1/messages/{$ulid}");

    Event::assertDispatched(MessageDeleted::class);
});

test('reacting broadcasts MessageReacted', function () {
    [$conversation, $me, $them] = dmForBroadcast();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hi'])->json('data.ulid');

    Event::fake([MessageReacted::class]);
    $this->actingAs($me)->postJson("/api/v1/messages/{$ulid}/reactions", ['emoji' => '👍']);

    Event::assertDispatched(MessageReacted::class);
});

test('reading broadcasts a ReadReceipt', function () {
    [$conversation, $me, $them] = dmForBroadcast();
    $ulid = $this->actingAs($them)->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hi'])->json('data.ulid');

    Event::fake([ReadReceipt::class]);
    $this->actingAs($me)->postJson("/api/v1/conversations/{$conversation->ulid}/read", ['message_ulid' => $ulid]);

    Event::assertDispatched(ReadReceipt::class, fn (ReadReceipt $e) => $e->reader->is($me->personalProfile));
});
