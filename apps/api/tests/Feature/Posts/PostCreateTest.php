<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Profile;

function makePostData(string $type, Profile $author): array
{
    return match ($type) {
        'text' => ['type' => 'text', 'body' => 'Hello world'],
        'link' => ['type' => 'link', 'payload' => ['url' => 'https://example.com', 'title' => 'Example']],
        'image' => ['type' => 'image', 'body' => 'caption', 'media_ids' => [Media::factory()->create(['profile_id' => $author->id])->ulid]],
        'quote' => ['type' => 'quote', 'body' => 'Great point!', 'parent_post_id' => Post::factory()->create()->ulid],
        'repost' => ['type' => 'repost', 'parent_post_id' => Post::factory()->create()->ulid],
        'typewriter' => ['type' => 'typewriter', 'payload' => ['text' => 'One letter at a time', 'speed' => 40]],
        'magnifier' => ['type' => 'magnifier', 'payload' => ['text' => 'Look closer']],
        'secret' => ['type' => 'secret', 'payload' => ['secret_text' => 'shh']],
        'checkin' => ['type' => 'checkin', 'payload' => ['place_name' => 'Vilakazi Street'], 'lat' => -26.2381, 'lng' => 27.8985, 'city' => 'Soweto', 'country_code' => 'za'],
    };
}

test('a post of each type can be created with a valid payload', function (string $type) {
    $user = userWithProfile();
    $data = makePostData($type, $user->personalProfile);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', $data)
        ->assertCreated()
        ->assertJsonPath('data.type', $type)
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.profile.handle', $user->personalProfile->handle);

    expect(Post::where('type', $type)->where('profile_id', $user->personalProfile->id)->exists())->toBeTrue();
})->with(['text', 'link', 'image', 'quote', 'repost', 'typewriter', 'magnifier', 'secret', 'checkin']);

test('invalid post data is rejected with 422', function (array $data, string $errorKey) {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);
})->with([
    'unknown type' => [['type' => 'hologram', 'body' => 'hi'], 'type'],
    'text without body' => [['type' => 'text'], 'body'],
    'text with payload' => [['type' => 'text', 'body' => 'hi', 'payload' => ['x' => 1]], 'payload'],
    'text with media' => [['type' => 'text', 'body' => 'hi', 'media_ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV']], 'media_ids'],
    'link without url' => [['type' => 'link', 'payload' => ['title' => 'no url']], 'payload.url'],
    'link with invalid url' => [['type' => 'link', 'payload' => ['url' => 'not-a-url']], 'payload.url'],
    'image without media' => [['type' => 'image'], 'media_ids'],
    'image with too many media' => [['type' => 'image', 'media_ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FA1', '01ARZ3NDEKTSV4RRFFQ69G5FA2', '01ARZ3NDEKTSV4RRFFQ69G5FA3', '01ARZ3NDEKTSV4RRFFQ69G5FA4', '01ARZ3NDEKTSV4RRFFQ69G5FA5']], 'media_ids'],
    'quote without parent' => [['type' => 'quote', 'body' => 'hi'], 'parent_post_id'],
    'repost with body' => [['type' => 'repost', 'body' => 'nope', 'parent_post_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], 'body'],
    'typewriter without text' => [['type' => 'typewriter', 'payload' => ['speed' => 10]], 'payload.text'],
    'magnifier without text' => [['type' => 'magnifier', 'payload' => ['foo' => 'bar']], 'payload.text'],
    'secret without secret text' => [['type' => 'secret', 'payload' => ['foo' => 'bar']], 'payload.secret_text'],
    'checkin without place name' => [['type' => 'checkin', 'payload' => ['foo' => 'bar']], 'payload.place_name'],
]);

test('guests cannot create posts', function () {
    $this->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'hi'])
        ->assertUnauthorized();
});

test('media belonging to another profile cannot be attached', function () {
    $user = userWithProfile();
    $other = userWithProfile();
    $media = Media::factory()->create(['profile_id' => $other->personalProfile->id]);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'image', 'media_ids' => [$media->ulid]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media_ids');
});

test('media already attached to a post cannot be reused', function () {
    $user = userWithProfile();
    $media = Media::factory()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'image', 'media_ids' => [$media->ulid]])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'image', 'media_ids' => [$media->ulid]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media_ids');
});

test('a magnifier image must belong to the author', function () {
    $user = userWithProfile();
    $other = userWithProfile();
    $media = Media::factory()->create(['profile_id' => $other->personalProfile->id]);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'magnifier',
            'payload' => ['text' => 'Look closer', 'image_media_id' => $media->ulid],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payload.image_media_id');
});

test('a magnifier image owned by the author is attached to the post', function () {
    $user = userWithProfile();
    $media = Media::factory()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', [
            'type' => 'magnifier',
            'payload' => ['text' => 'Look closer', 'image_media_id' => $media->ulid],
        ])
        ->assertCreated()
        ->assertJsonPath('data.media.0.ulid', $media->ulid);

    expect($media->refresh()->mediable_id)->toBe(Post::first()->id);
});

test('a quote requires a published parent post', function () {
    $user = userWithProfile();
    $draftParent = Post::factory()->draft()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'quote', 'body' => 'hi', 'parent_post_id' => $draftParent->ulid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_post_id');
});

test('a quote includes its parent one level deep in the response', function () {
    $user = userWithProfile();
    $parent = Post::factory()->create(['body' => 'original']);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'quote', 'body' => 'my take', 'parent_post_id' => $parent->ulid])
        ->assertCreated()
        ->assertJsonPath('data.parent.ulid', $parent->ulid)
        ->assertJsonPath('data.parent.body', 'original');
});

test('reposting the same post twice returns 409 and increments the counter once', function () {
    $user = userWithProfile();
    $parent = Post::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'repost', 'parent_post_id' => $parent->ulid])
        ->assertCreated();

    expect($parent->refresh()->reposts_count)->toBe(1);

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'repost', 'parent_post_id' => $parent->ulid])
        ->assertConflict();

    expect($parent->refresh()->reposts_count)->toBe(1);
});

test('publishing increments the author posts_count while drafts do not', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'published'])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'draft', 'status' => 'draft'])
        ->assertCreated();

    expect($user->personalProfile->refresh()->posts_count)->toBe(1);
});

test('posts are authored by the active profile from the X-Profile-Id header', function () {
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'as business'])
        ->assertCreated()
        ->assertJsonPath('data.profile.handle', $business->handle);
});

test('a checkin stores its location fields', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts', makePostData('checkin', $user->personalProfile))
        ->assertCreated()
        ->assertJsonPath('data.city', 'Soweto')
        ->assertJsonPath('data.country_code', 'ZA')
        ->assertJsonPath('data.payload.place_name', 'Vilakazi Street');

    expect((float) Post::first()->lat)->toBe(-26.2381);
});
