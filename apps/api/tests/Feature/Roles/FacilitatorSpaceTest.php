<?php

use App\Models\Conversation;
use App\Models\User;

function createGroupAs(User $creator, User $member): Conversation
{
    $ulid = test()->actingAs($creator)->postJson('/api/v1/conversations', [
        'kind' => 'group',
        'title' => 'My Space',
        'member_ulids' => [$member->profiles()->first()->ulid],
    ])->assertCreated()->json('data.ulid');

    return Conversation::firstWhere('ulid', $ulid);
}

test('a facilitator-owned group is auto-approved (a Space)', function () {
    $facilitator = userWithProfile(['is_facilitator' => true]);
    $member = userWithProfile();

    $conversation = createGroupAs($facilitator, $member);

    expect($conversation->approval_status)->toBe(Conversation::APPROVAL_APPROVED)
        ->and($conversation->approved_at)->not->toBeNull();
});

test('a non-facilitator group still pends admin approval', function () {
    $user = userWithProfile();
    $member = userWithProfile();

    $conversation = createGroupAs($user, $member);

    expect($conversation->approval_status)->toBe(Conversation::APPROVAL_PENDING)
        ->and($conversation->approved_at)->toBeNull();
});

test('a facilitator can message their Space immediately', function () {
    $facilitator = userWithProfile(['is_facilitator' => true]);
    $member = userWithProfile();

    $conversation = createGroupAs($facilitator, $member);

    $this->actingAs($facilitator)
        ->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'Welcome to the space!'])
        ->assertSuccessful();
});
