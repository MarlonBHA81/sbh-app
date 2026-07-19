<?php

use App\Models\Post;

test('a post can be created as a win', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'We signed our first big customer today!',
            'is_win' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_win', true);
});

test('the wins feed returns only win posts', function () {
    $author = userWithProfile();
    $authorProfile = $author->profiles()->first();

    Post::factory()->for($authorProfile, 'profile')->create([
        'is_win' => true, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);
    Post::factory()->for($authorProfile, 'profile')->create([
        'is_win' => false, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/wins')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_win', true);
});

test('the wins feed requires authentication', function () {
    $this->getJson('/api/v1/feeds/wins')->assertUnauthorized();
});
