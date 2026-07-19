<?php

use App\Models\CoachMessage;

test('sending a message returns a coach reply and persists both turns', function () {
    $user = userWithProfile(['name' => 'Thabo', 'category' => 'Retail']);

    $this->actingAs($user)
        ->postJson('/api/v1/coach/messages', ['body' => 'How should I price my products?'])
        ->assertCreated()
        ->assertJsonPath('data.user.role', 'user')
        ->assertJsonPath('data.user.body', 'How should I price my products?')
        ->assertJsonPath('data.assistant.role', 'assistant');

    // Null driver (default in tests) yields a non-empty canned reply.
    $reply = CoachMessage::query()->where('role', 'assistant')->value('body');
    expect($reply)->not->toBeEmpty();

    expect(CoachMessage::query()->count())->toBe(2);
});

test('the coach reply is tailored to the topic keyword', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/coach/messages', ['body' => 'Where can I find funding or a grant?'])
        ->assertCreated();

    $reply = CoachMessage::query()->where('role', 'assistant')->value('body');
    expect(strtolower($reply))->toContain('funding');
});

test('the conversation survives reload', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/coach/messages', ['body' => 'First question'])
        ->assertCreated();

    $this->actingAs($user)
        ->getJson('/api/v1/coach')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'First question');
});

test('an empty message is rejected', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/coach/messages', ['body' => ''])
        ->assertStatus(422);
});

test('the conversation can be reset', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/coach/messages', ['body' => 'Hello'])->assertCreated();
    $this->actingAs($user)->deleteJson('/api/v1/coach')->assertNoContent();
    $this->actingAs($user)->getJson('/api/v1/coach')->assertJsonCount(0, 'data');
});

test('a member only sees their own coach conversation', function () {
    $alice = userWithProfile();
    $bob = userWithProfile();

    $this->actingAs($alice)->postJson('/api/v1/coach/messages', ['body' => "Alice's question"])->assertCreated();

    $this->actingAs($bob)
        ->getJson('/api/v1/coach')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the coach requires authentication', function () {
    $this->getJson('/api/v1/coach')->assertUnauthorized();
});
