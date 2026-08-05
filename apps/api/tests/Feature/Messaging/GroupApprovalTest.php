<?php

use App\Filament\Resources\GroupApprovals\GroupApprovalResource;
use App\Models\Conversation;
use App\Notifications\GroupApprovalDecided;
use Illuminate\Support\Facades\Notification;

function makePendingGroup(): array
{
    $owner = userWithProfile();
    $member = userWithProfile();

    $ulid = test()->actingAs($owner)->postJson('/api/v1/conversations', [
        'kind' => 'group',
        'title' => 'New Hustlers',
        'member_ulids' => [$member->personalProfile->ulid],
    ])->assertCreated()->json('data.ulid');

    return [$owner, $member, Conversation::firstWhere('ulid', $ulid)];
}

test('a new group starts pending admin approval', function () {
    [$owner, , $conversation] = makePendingGroup();

    expect($conversation->approval_status)->toBe(Conversation::APPROVAL_PENDING);

    $this->actingAs($owner)
        ->getJson("/api/v1/conversations/{$conversation->ulid}")
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'pending');
});

test('messages to a pending group are rejected', function () {
    [$owner, , $conversation] = makePendingGroup();

    $this->actingAs($owner)
        ->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'hello?'])
        ->assertStatus(422);
});

test('approval unblocks the group and notifies the creator', function () {
    Notification::fake();

    [$owner, , $conversation] = makePendingGroup();

    GroupApprovalResource::decide($conversation, true);

    expect($conversation->refresh()->approval_status)->toBe(Conversation::APPROVAL_APPROVED);

    Notification::assertSentTo(
        $owner,
        GroupApprovalDecided::class,
        fn ($notification) => $notification->type() === 'group_approved',
    );

    $this->actingAs($owner)
        ->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'we are live'])
        ->assertCreated();
});

test('a rejected group stays locked and the creator is told', function () {
    Notification::fake();

    [$owner, , $conversation] = makePendingGroup();

    GroupApprovalResource::decide($conversation, false);

    expect($conversation->refresh()->approval_status)->toBe(Conversation::APPROVAL_REJECTED);

    Notification::assertSentTo(
        $owner,
        GroupApprovalDecided::class,
        fn ($notification) => $notification->type() === 'group_rejected',
    );

    $this->actingAs($owner)
        ->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'anyone?'])
        ->assertStatus(422);
});

test('DMs are unaffected by group approval', function () {
    $a = userWithProfile();
    $b = userWithProfile();

    $ulid = $this->actingAs($a)->postJson('/api/v1/conversations', [
        'kind' => 'dm',
        'profile_ulid' => $b->personalProfile->ulid,
    ])->assertCreated()->json('data.ulid');

    $this->actingAs($a)
        ->postJson("/api/v1/conversations/{$ulid}/messages", ['body' => 'hi'])
        ->assertCreated();
});
