<?php

use App\Models\Conversation;
use App\Models\Masterclass;

function chatRoom(?int $createdBy = null): Masterclass
{
    return Masterclass::create([
        'title' => 'Chat room',
        'description' => 'A room with chat.',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
        'is_published' => true,
        'created_by' => $createdBy,
    ]);
}

test('enrolling joins the room chat and the member can post', function () {
    $room = chatRoom();
    $member = userWithProfile();

    $res = $this->actingAs($member)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/enrol")
        ->assertCreated();

    $chatUlid = $res->json('data.chat_conversation');
    expect($chatUlid)->not->toBeNull();

    // The room conversation is a pre-approved group.
    $conversation = Conversation::where('ulid', $chatUlid)->first();
    expect($conversation->kind)->toBe(Conversation::KIND_GROUP)
        ->and($conversation->isApproved())->toBeTrue();

    // The enrolled member can send a message.
    $this->actingAs($member)
        ->postJson("/api/v1/conversations/{$chatUlid}/messages", ['body' => 'Hi everyone!'])
        ->assertCreated();
});

test('a non-enrolled user cannot post in the room chat', function () {
    $room = chatRoom();
    $member = userWithProfile();
    $chatUlid = $this->actingAs($member)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/enrol")
        ->json('data.chat_conversation');

    $stranger = userWithProfile();
    $this->actingAs($stranger)
        ->postJson("/api/v1/conversations/{$chatUlid}/messages", ['body' => 'let me in'])
        ->assertStatus(404);
});

test('withdrawing leaves the room chat', function () {
    $room = chatRoom();
    $member = userWithProfile();
    $chatUlid = $this->actingAs($member)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/enrol")
        ->json('data.chat_conversation');

    $this->actingAs($member)
        ->deleteJson("/api/v1/masterclasses/{$room->ulid}/enrol")
        ->assertNoContent();

    // No longer an active participant → can't post.
    $this->actingAs($member)
        ->postJson("/api/v1/conversations/{$chatUlid}/messages", ['body' => 'still here?'])
        ->assertStatus(403);
});

test('the masterclass creator owns the room chat', function () {
    $admin = adminWithProfile();
    $room = chatRoom($admin->id);

    // A member enrolling triggers the chat; the admin (creator) is its owner.
    $member = userWithProfile();
    $chatUlid = $this->actingAs($member)
        ->postJson("/api/v1/masterclasses/{$room->ulid}/enrol")
        ->json('data.chat_conversation');

    $conversation = Conversation::where('ulid', $chatUlid)->first();
    expect($conversation->created_by_profile_id)->toBe($admin->personalProfile->id);
});
