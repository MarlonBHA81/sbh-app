<?php

use App\Models\Post;
use App\Models\Profile;
use App\Models\User;

test('a user can export their own data', function () {
    $user = userWithProfile();
    Post::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'body' => 'My exportable post',
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/me/export')
        ->assertOk()
        ->assertJsonPath('data.account.email', $user->email)
        ->assertJsonPath('data.posts.0.body', 'My exportable post');
});

test('deleting an account requires the correct password', function () {
    $user = userWithProfile([], ['password' => bcrypt('correct-horse')]);

    $this->actingAs($user)
        ->deleteJson('/api/v1/me/account', ['password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    expect(User::find($user->id))->not->toBeNull();
});

test('a user can delete their account with the correct password', function () {
    $user = userWithProfile([], ['password' => bcrypt('correct-horse')]);
    $profileId = $user->personalProfile->id;

    $this->actingAs($user)
        ->deleteJson('/api/v1/me/account', ['password' => 'correct-horse'])
        ->assertNoContent();

    expect(User::find($user->id))->toBeNull()
        ->and(Profile::find($profileId))->toBeNull();
});

test('a social-only account deletes by confirming its handle', function () {
    $user = userWithProfile([], ['password' => null]);
    $handle = $user->personalProfile->handle;

    $this->actingAs($user)
        ->deleteJson('/api/v1/me/account', ['confirm_handle' => 'wrong'])
        ->assertStatus(422);

    $this->actingAs($user)
        ->deleteJson('/api/v1/me/account', ['confirm_handle' => $handle])
        ->assertNoContent();

    expect(User::find($user->id))->toBeNull();
});

test('me exposes whether the account has a password', function () {
    $withPw = userWithProfile([], ['password' => bcrypt('x')]);
    $this->actingAs($withPw)->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('user.has_password', true);
});
