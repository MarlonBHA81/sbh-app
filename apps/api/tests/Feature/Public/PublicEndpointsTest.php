<?php

use App\Models\BusinessCategory;
use App\Models\Media;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Topic;
use App\Models\User;

test('the public profile endpoint returns the limited public shape', function () {
    $category = BusinessCategory::factory()->create(['slug' => 'cafes', 'name' => 'Cafés']);
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->business()->create([
        'handle' => 'public_shop',
        'name' => 'Public Shop',
        'bio' => 'We sell things.',
        'business_category_id' => $category->id,
    ]);

    $this->getJson('/api/v1/public/profiles/public_shop')
        ->assertOk()
        ->assertExactJson(['data' => [
            'handle' => 'public_shop',
            'name' => 'Public Shop',
            'bio' => 'We sell things.',
            'avatar_url' => null,
            'cover_url' => null,
            'kind' => Profile::KIND_BUSINESS,
            'is_verified' => false,
            'followers_count' => 0,
            'posts_count' => 0,
            'business_category' => ['slug' => 'cafes', 'name' => 'Cafés'],
        ]]);
});

test('a private profile is a 404 on the public endpoint', function () {
    Profile::factory()->private()->create(['handle' => 'private_one']);

    $this->getJson('/api/v1/public/profiles/private_one')->assertNotFound();
});

test('a profile owned by a banned user is a 404 on the public endpoint', function () {
    $user = User::factory()->create(['banned_at' => now()]);
    Profile::factory()->for($user)->create(['handle' => 'banned_owner']);

    $this->getJson('/api/v1/public/profiles/banned_owner')->assertNotFound();
});

test('an unknown handle is a 404 on the public endpoint', function () {
    $this->getJson('/api/v1/public/profiles/nobody')->assertNotFound();
});

test('the public post endpoint returns the SEO shape', function () {
    $profile = Profile::factory()->create(['handle' => 'poster', 'name' => 'Poster']);
    $topic = Topic::factory()->create(['slug' => 'news', 'name' => 'News']);
    $post = Post::factory()->for($profile)->create(['body' => 'hello world']);
    $post->topics()->attach($topic);
    Media::factory()->create(['mediable_type' => $post->getMorphClass(), 'mediable_id' => $post->id]);

    $response = $this->getJson("/api/v1/public/posts/{$post->ulid}")
        ->assertOk()
        ->assertJsonPath('data.ulid', $post->ulid)
        ->assertJsonPath('data.body', 'hello world')
        ->assertJsonPath('data.sensitive', false)
        ->assertJsonPath('data.profile.handle', 'poster')
        ->assertJsonPath('data.topics.0.slug', 'news');

    $response->assertJsonStructure(['data' => [
        'ulid', 'type', 'body', 'sensitive',
        'profile' => ['handle', 'name', 'avatar_url', 'is_verified'],
        'media' => [['type', 'url', 'thumb_url', 'width', 'height']],
        'likes_count', 'comments_count', 'reposts_count', 'views_count',
        'published_at', 'topics' => [['slug', 'name']],
    ]]);
});

test('a sensitive post is returned but flagged', function () {
    $post = Post::factory()->create(['sensitive' => true]);

    $this->getJson("/api/v1/public/posts/{$post->ulid}")
        ->assertOk()
        ->assertJsonPath('data.sensitive', true);
});

test('a secret post never leaks its payload on the public endpoint', function () {
    $post = Post::factory()->secret('the hidden truth')->create();

    $response = $this->getJson("/api/v1/public/posts/{$post->ulid}")->assertOk();

    expect($response->json('data'))->not->toHaveKey('payload')
        ->and($response->json('data.body'))->toBeNull();
    expect(json_encode($response->json()))->not->toContain('the hidden truth');
});

test('a draft post is a 404 on the public endpoint', function () {
    $post = Post::factory()->draft()->create();

    $this->getJson("/api/v1/public/posts/{$post->ulid}")->assertNotFound();
});

test('a followers-only post is a 404 on the public endpoint', function () {
    $post = Post::factory()->followersOnly()->create();

    $this->getJson("/api/v1/public/posts/{$post->ulid}")->assertNotFound();
});

test('a post by a private profile is a 404 on the public endpoint', function () {
    $profile = Profile::factory()->private()->create();
    $post = Post::factory()->for($profile)->create();

    $this->getJson("/api/v1/public/posts/{$post->ulid}")->assertNotFound();
});

test('a post by a banned author is a 404 on the public endpoint', function () {
    $user = User::factory()->create(['banned_at' => now()]);
    $profile = Profile::factory()->for($user)->create();
    $post = Post::factory()->for($profile)->create();

    $this->getJson("/api/v1/public/posts/{$post->ulid}")->assertNotFound();
});

test('the public endpoints advertise their rate limit', function () {
    $profile = Profile::factory()->create(['handle' => 'ratelimited']);

    $this->getJson('/api/v1/public/profiles/ratelimited')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 60);
});

test('the public endpoints require no authentication', function () {
    $profile = Profile::factory()->create(['handle' => 'anon_ok']);

    // No actingAs — a guest gets a 200, not a 401.
    $this->getJson('/api/v1/public/profiles/anon_ok')->assertOk();
});
