<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use Database\Seeders\XpActionSeeder;

test('a post can be created as a question', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'text',
            'body' => 'How do I register for VAT?',
            'is_question' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_question', true)
        ->assertJsonPath('data.is_answered', false);
});

test('the questions feed returns only question posts', function () {
    $author = userWithProfile();
    $authorProfile = $author->profiles()->first();

    Post::factory()->for($authorProfile, 'profile')->create([
        'is_question' => true, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);
    Post::factory()->for($authorProfile, 'profile')->create([
        'is_question' => false, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/questions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_question', true);
});

test('marking a helpful reply answers the question', function () {
    $this->seed(XpActionSeeder::class);

    $author = userWithProfile();
    $authorProfile = $author->profiles()->first();
    $post = Post::factory()->for($authorProfile, 'profile')->create([
        'is_question' => true, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);

    $answerer = userWithProfile();
    $comment = Comment::factory()
        ->for($answerer->profiles()->first(), 'profile')
        ->for($post)
        ->create();

    $this->actingAs($author)
        ->postJson("/api/v1/comments/{$comment->ulid}/helpful")
        ->assertOk();

    expect($post->fresh()->answered_at)->not->toBeNull();

    // The feed reflects the answered state.
    $this->actingAs($author)
        ->getJson('/api/v1/feeds/questions?answered=1')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_answered', true);
});

test('unmarking the last helpful reply reopens the question', function () {
    $this->seed(XpActionSeeder::class);

    $author = userWithProfile();
    $post = Post::factory()->for($author->profiles()->first(), 'profile')->create([
        'is_question' => true, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);

    $answerer = userWithProfile();
    $comment = Comment::factory()
        ->for($answerer->profiles()->first(), 'profile')
        ->for($post)
        ->create();

    $this->actingAs($author)->postJson("/api/v1/comments/{$comment->ulid}/helpful")->assertOk();
    expect($post->fresh()->isAnswered())->toBeTrue();

    $this->actingAs($author)->deleteJson("/api/v1/comments/{$comment->ulid}/helpful")->assertOk();
    expect($post->fresh()->isAnswered())->toBeFalse();
});

test('the open-questions filter excludes answered questions', function () {
    $this->seed(XpActionSeeder::class);

    $author = userWithProfile();
    $authorProfile = $author->profiles()->first();

    $answered = Post::factory()->for($authorProfile, 'profile')->create([
        'is_question' => true, 'answered_at' => now(), 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);
    $open = Post::factory()->for($authorProfile, 'profile')->create([
        'is_question' => true, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
    ]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson('/api/v1/feeds/questions?answered=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ulid', $open->ulid);
});
