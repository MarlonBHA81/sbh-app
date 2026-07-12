<?php

use App\Models\Post;
use App\Models\PostStatsDaily;
use Illuminate\Support\Str;

test('seeing a post counts one view and bumps the daily stat', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($viewer)->postJson('/api/v1/posts/seen', ['ulids' => [$post->ulid]])
        ->assertNoContent();

    expect($post->refresh()->views_count)->toBe(1);

    $stat = PostStatsDaily::query()->where('post_id', $post->id)->first();
    expect((int) $stat->views)->toBe(1);
});

test('a post seen again the same day by the same profile is not recounted', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($viewer)->postJson('/api/v1/posts/seen', ['ulids' => [$post->ulid]])->assertNoContent();
    $this->actingAs($viewer)->postJson('/api/v1/posts/seen', ['ulids' => [$post->ulid]])->assertNoContent();

    expect($post->refresh()->views_count)->toBe(1);
});

test('two different profiles each count a view', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs(userWithProfile())->postJson('/api/v1/posts/seen', ['ulids' => [$post->ulid]])->assertNoContent();
    $this->actingAs(userWithProfile())->postJson('/api/v1/posts/seen', ['ulids' => [$post->ulid]])->assertNoContent();

    expect($post->refresh()->views_count)->toBe(2);
});

test('invisible posts are not counted', function () {
    $viewer = userWithProfile();
    $author = userWithProfile();
    $draft = Post::factory()->draft()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($viewer)->postJson('/api/v1/posts/seen', ['ulids' => [$draft->ulid]])->assertNoContent();

    expect($draft->refresh()->views_count)->toBe(0);
});

test('the seen batch is capped at twenty ulids', function () {
    $viewer = userWithProfile();

    $ulids = collect(range(1, 21))->map(fn () => (string) Str::ulid())->all();

    $this->actingAs($viewer)->postJson('/api/v1/posts/seen', ['ulids' => $ulids])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ulids');
});
