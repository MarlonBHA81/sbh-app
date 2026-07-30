<?php

use App\Models\Conversation;

/**
 * A Space owner (facilitator) appoints a Manager (the admin role); the Manager
 * can assign access — add/remove members — but cannot remove the owner.
 */
function spaceWith(array &$actors): Conversation
{
    $owner = userWithProfile(['is_facilitator' => true]);
    $manager = userWithProfile();
    $member = userWithProfile();

    $ulid = test()->actingAs($owner)->postJson('/api/v1/conversations', [
        'kind' => 'group',
        'title' => 'The Space',
        'member_ulids' => [
            $manager->profiles()->first()->ulid,
            $member->profiles()->first()->ulid,
        ],
    ])->assertCreated()->json('data.ulid');

    $actors = ['owner' => $owner, 'manager' => $manager, 'member' => $member];

    return Conversation::firstWhere('ulid', $ulid);
}

test('an owner can appoint a manager using the manager alias', function () {
    $actors = [];
    $space = spaceWith($actors);
    $managerProfile = $actors['manager']->profiles()->first();

    $this->actingAs($actors['owner'])
        ->postJson("/api/v1/conversations/{$space->ulid}/participants/{$managerProfile->ulid}/role", [
            'role' => 'manager',
        ])
        ->assertOk();

    expect($space->participantFor($managerProfile)->isManager())->toBeTrue();
});

test('a manager can add and remove plain members', function () {
    $actors = [];
    $space = spaceWith($actors);
    $managerProfile = $actors['manager']->profiles()->first();
    $memberProfile = $actors['member']->profiles()->first();

    // Owner promotes.
    $this->actingAs($actors['owner'])
        ->postJson("/api/v1/conversations/{$space->ulid}/participants/{$managerProfile->ulid}/role", ['role' => 'manager'])
        ->assertOk();

    // Manager adds a new member.
    $newcomer = userWithProfile();
    $this->actingAs($actors['manager'])
        ->postJson("/api/v1/conversations/{$space->ulid}/participants", [
            'profile_ulids' => [$newcomer->profiles()->first()->ulid],
        ])
        ->assertOk();

    // Manager removes a plain member.
    $this->actingAs($actors['manager'])
        ->deleteJson("/api/v1/conversations/{$space->ulid}/participants/{$memberProfile->ulid}")
        ->assertNoContent();
});

test('a manager cannot remove the owner', function () {
    $actors = [];
    $space = spaceWith($actors);
    $managerProfile = $actors['manager']->profiles()->first();
    $ownerProfile = $actors['owner']->profiles()->first();

    $this->actingAs($actors['owner'])
        ->postJson("/api/v1/conversations/{$space->ulid}/participants/{$managerProfile->ulid}/role", ['role' => 'manager'])
        ->assertOk();

    $this->actingAs($actors['manager'])
        ->deleteJson("/api/v1/conversations/{$space->ulid}/participants/{$ownerProfile->ulid}")
        ->assertForbidden();
});

test('a plain member cannot add participants', function () {
    $actors = [];
    $space = spaceWith($actors);
    $newcomer = userWithProfile();

    $this->actingAs($actors['member'])
        ->postJson("/api/v1/conversations/{$space->ulid}/participants", [
            'profile_ulids' => [$newcomer->profiles()->first()->ulid],
        ])
        ->assertForbidden();
});
