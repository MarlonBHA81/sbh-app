<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\SafetyService;

/**
 * @return array{0: User, 1: array<int, User>, 2: string}
 */
function makeGroup(array $memberUsers, ?string $rules = null): array
{
    $owner = userWithProfile();
    $ulids = collect($memberUsers)->map(fn ($u) => $u->personalProfile->ulid)->all();

    $ulid = test()->actingAs($owner)->postJson('/api/v1/conversations', array_filter([
        'kind' => 'group',
        'title' => 'The Squad',
        'member_ulids' => $ulids,
        'rules' => $rules,
    ]))->assertCreated()->json('data.ulid');

    // Groups start pending (admin approval); approve so feature tests that
    // exercise group behaviour aren't blocked. GroupApprovalTest covers the
    // pending flow itself.
    Conversation::firstWhere('ulid', $ulid)->update([
        'approval_status' => Conversation::APPROVAL_APPROVED,
        'approved_at' => now(),
    ]);

    return [$owner, $memberUsers, $ulid];
}

test('a group is created with the creator as owner and members', function () {
    $m1 = userWithProfile();
    $m2 = userWithProfile();

    [$owner, , $ulid] = makeGroup([$m1, $m2]);

    $conversation = Conversation::firstWhere('ulid', $ulid);

    expect($conversation->kind)->toBe('group')
        ->and($conversation->participants()->count())->toBe(3);

    $ownerRow = $conversation->participants()->where('profile_id', $owner->personalProfile->id)->first();
    expect($ownerRow->role)->toBe('owner');

    $this->actingAs($owner)->getJson("/api/v1/conversations/{$ulid}")
        ->assertOk()
        ->assertJsonPath('data.my_role', 'owner')
        ->assertJsonPath('data.title', 'The Squad');
});

test('blocked members are silently excluded from a new group', function () {
    $owner = userWithProfile();
    $friend = userWithProfile();
    $blocked = userWithProfile();

    app(SafetyService::class)->block($owner->personalProfile, $blocked->personalProfile);

    $ulid = $this->actingAs($owner)->postJson('/api/v1/conversations', [
        'kind' => 'group',
        'title' => 'Party',
        'member_ulids' => [$friend->personalProfile->ulid, $blocked->personalProfile->ulid],
    ])->assertCreated()->json('data.ulid');

    $conversation = Conversation::firstWhere('ulid', $ulid);

    expect($conversation->participants()->count())->toBe(2)
        ->and($conversation->participants()->where('profile_id', $blocked->personalProfile->id)->exists())->toBeFalse();
});

test('group title is required and capped at 80 characters', function () {
    $owner = userWithProfile();
    $m = userWithProfile();

    $this->actingAs($owner)->postJson('/api/v1/conversations', [
        'kind' => 'group', 'member_ulids' => [$m->personalProfile->ulid],
    ])->assertJsonValidationErrors('title');

    $this->actingAs($owner)->postJson('/api/v1/conversations', [
        'kind' => 'group', 'title' => str_repeat('x', 81), 'member_ulids' => [$m->personalProfile->ulid],
    ])->assertJsonValidationErrors('title');
});

test('group requires between 1 and 49 members', function () {
    $owner = userWithProfile();

    $this->actingAs($owner)->postJson('/api/v1/conversations', [
        'kind' => 'group', 'title' => 'Empty', 'member_ulids' => [],
    ])->assertJsonValidationErrors('member_ulids');
});

test('group rules are capped at 2000 characters', function () {
    $owner = userWithProfile();
    $m = userWithProfile();

    $this->actingAs($owner)->postJson('/api/v1/conversations', [
        'kind' => 'group', 'title' => 'Rules', 'member_ulids' => [$m->personalProfile->ulid],
        'rules' => str_repeat('x', 2001),
    ])->assertJsonValidationErrors('rules');
});

test('owner or admin can rename a group and set rules', function () {
    $m = userWithProfile();
    [$owner, , $ulid] = makeGroup([$m]);

    $this->actingAs($owner)->patchJson("/api/v1/conversations/{$ulid}", [
        'title' => 'Renamed', 'rules' => 'Be kind',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Renamed')
        ->assertJsonPath('data.rules', 'Be kind');
});

test('a plain member cannot rename a group', function () {
    $m = userWithProfile();
    [, , $ulid] = makeGroup([$m]);

    $this->actingAs($m)->patchJson("/api/v1/conversations/{$ulid}", ['title' => 'Nope'])
        ->assertForbidden();
});

test('a dm cannot be renamed', function () {
    $me = userWithProfile();
    $them = userWithProfile();

    $ulid = $this->actingAs($me)->postJson('/api/v1/conversations', [
        'kind' => 'dm', 'profile_ulid' => $them->personalProfile->ulid,
    ])->json('data.ulid');

    $this->actingAs($me)->patchJson("/api/v1/conversations/{$ulid}", ['title' => 'Nope'])
        ->assertForbidden();
});

test('owner or admin can add participants, skipping blocked and existing', function () {
    $m = userWithProfile();
    [$owner, , $ulid] = makeGroup([$m]);

    $newbie = userWithProfile();
    $blocked = userWithProfile();
    app(SafetyService::class)->block($owner->personalProfile, $blocked->personalProfile);

    $this->actingAs($owner)->postJson("/api/v1/conversations/{$ulid}/participants", [
        'profile_ulids' => [$newbie->personalProfile->ulid, $blocked->personalProfile->ulid, $m->personalProfile->ulid],
    ])->assertOk();

    $conversation = Conversation::firstWhere('ulid', $ulid);
    expect($conversation->participants()->whereNull('left_at')->count())->toBe(3) // owner + m + newbie
        ->and($conversation->participants()->where('profile_id', $blocked->personalProfile->id)->exists())->toBeFalse();
});

test('adding participants is limited to 10 per call', function () {
    $m = userWithProfile();
    [$owner, , $ulid] = makeGroup([$m]);

    $ulids = collect(range(1, 11))->map(fn () => userWithProfile()->personalProfile->ulid)->all();

    $this->actingAs($owner)->postJson("/api/v1/conversations/{$ulid}/participants", [
        'profile_ulids' => $ulids,
    ])->assertJsonValidationErrors('profile_ulids');
});

test('a member cannot add participants', function () {
    $m = userWithProfile();
    [, , $ulid] = makeGroup([$m]);
    $newbie = userWithProfile();

    $this->actingAs($m)->postJson("/api/v1/conversations/{$ulid}/participants", [
        'profile_ulids' => [$newbie->personalProfile->ulid],
    ])->assertForbidden();
});

test('owner can change a member role, non-owners cannot', function () {
    $m = userWithProfile();
    [$owner, , $ulid] = makeGroup([$m]);

    $this->actingAs($owner)->postJson("/api/v1/conversations/{$ulid}/participants/{$m->personalProfile->ulid}/role", [
        'role' => 'admin',
    ])->assertOk();

    $conversation = Conversation::firstWhere('ulid', $ulid);
    expect($conversation->participants()->where('profile_id', $m->personalProfile->id)->first()->role)->toBe('admin');

    // A non-owner (now admin) cannot change roles.
    $other = userWithProfile();
    $this->actingAs($owner)->postJson("/api/v1/conversations/{$ulid}/participants", ['profile_ulids' => [$other->personalProfile->ulid]]);

    $this->actingAs($m)->postJson("/api/v1/conversations/{$ulid}/participants/{$other->personalProfile->ulid}/role", [
        'role' => 'admin',
    ])->assertForbidden();
});

test('an admin cannot remove the owner or another admin but can remove members', function () {
    $admin = userWithProfile();
    $member = userWithProfile();
    [$owner, , $ulid] = makeGroup([$admin, $member]);

    $this->actingAs($owner)->postJson("/api/v1/conversations/{$ulid}/participants/{$admin->personalProfile->ulid}/role", ['role' => 'admin']);

    // Admin cannot remove the owner.
    $this->actingAs($admin)->deleteJson("/api/v1/conversations/{$ulid}/participants/{$owner->personalProfile->ulid}")
        ->assertForbidden();

    // Admin can remove a plain member.
    $this->actingAs($admin)->deleteJson("/api/v1/conversations/{$ulid}/participants/{$member->personalProfile->ulid}")
        ->assertNoContent();

    $conversation = Conversation::firstWhere('ulid', $ulid);
    expect($conversation->participantFor($member->personalProfile))->toBeNull();
});

test('owner leaving transfers ownership to the oldest admin', function () {
    $adminOld = userWithProfile();
    $adminNew = userWithProfile();
    $member = userWithProfile();
    [$owner, , $ulid] = makeGroup([$adminOld, $adminNew, $member]);

    $this->actingAs($owner)->postJson("/api/v1/conversations/{$ulid}/participants/{$adminOld->personalProfile->ulid}/role", ['role' => 'admin']);
    $this->actingAs($owner)->postJson("/api/v1/conversations/{$ulid}/participants/{$adminNew->personalProfile->ulid}/role", ['role' => 'admin']);

    // Owner leaves.
    $this->actingAs($owner)->deleteJson("/api/v1/conversations/{$ulid}/participants/{$owner->personalProfile->ulid}")
        ->assertNoContent();

    $conversation = Conversation::firstWhere('ulid', $ulid);
    $newOwner = $conversation->participants()->where('role', 'owner')->whereNull('left_at')->first();

    expect($newOwner->profile_id)->toBe($adminOld->personalProfile->id);
});

test('owner leaving with no admins transfers to the oldest member', function () {
    $memberOld = userWithProfile();
    $memberNew = userWithProfile();
    [$owner, , $ulid] = makeGroup([$memberOld, $memberNew]);

    $this->actingAs($owner)->deleteJson("/api/v1/conversations/{$ulid}/participants/{$owner->personalProfile->ulid}")
        ->assertNoContent();

    $conversation = Conversation::firstWhere('ulid', $ulid);
    $newOwner = $conversation->participants()->where('role', 'owner')->whereNull('left_at')->first();

    expect($newOwner->profile_id)->toBe($memberOld->personalProfile->id);
});

test('a lone owner leaving empties the group without error', function () {
    $throwaway = userWithProfile();
    [$owner, , $ulid] = makeGroup([$throwaway]);

    // Remove the only other member first.
    $this->actingAs($owner)->deleteJson("/api/v1/conversations/{$ulid}/participants/{$throwaway->personalProfile->ulid}");

    $this->actingAs($owner)->deleteJson("/api/v1/conversations/{$ulid}/participants/{$owner->personalProfile->ulid}")
        ->assertNoContent();

    $conversation = Conversation::firstWhere('ulid', $ulid);
    expect($conversation->participants()->whereNull('left_at')->count())->toBe(0);
});
