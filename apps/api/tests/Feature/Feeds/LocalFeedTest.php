<?php

use App\Models\Post;

function viewerInLocale(string $country, ?string $city = null)
{
    $user = userWithProfile();
    $user->personalProfile->forceFill([
        'country_code' => $country,
        'city' => $city,
    ])->save();

    return $user;
}

test('country-scope local feed returns public posts from the same country', function () {
    $me = viewerInLocale('AU', 'Sydney');
    $author = userWithProfile();

    $auPost = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'AU']);
    $usPost = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'US']);

    $res = $this->actingAs($me)->getJson('/api/v1/feeds/local')->assertOk();
    $ulids = collect($res->json('data'))->pluck('ulid');

    expect($ulids)->toContain($auPost->ulid)->not->toContain($usPost->ulid);
});

test('city-scope local feed matches on city within the same country', function () {
    $me = viewerInLocale('AU', 'Sydney');
    $author = userWithProfile();

    $sydney = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'AU', 'city' => 'Sydney']);
    $melbourne = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'AU', 'city' => 'Melbourne']);
    $sydneyElsewhere = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'US', 'city' => 'Sydney']);

    $res = $this->actingAs($me)->getJson('/api/v1/feeds/local?scope=city')->assertOk();
    $ulids = collect($res->json('data'))->pluck('ulid');

    expect($ulids)->toContain($sydney->ulid)
        ->not->toContain($melbourne->ulid)
        ->not->toContain($sydneyElsewhere->ulid);
});

test('local feed excludes followers-only, drafts and private-profile posts', function () {
    $me = viewerInLocale('AU', 'Sydney');
    $author = userWithProfile();
    $private = userWithProfile(['is_private' => true]);

    $visible = Post::factory()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'AU']);
    $followersOnly = Post::factory()->followersOnly()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'AU']);
    $draft = Post::factory()->draft()->create(['profile_id' => $author->personalProfile->id, 'country_code' => 'AU']);
    $privatePost = Post::factory()->create(['profile_id' => $private->personalProfile->id, 'country_code' => 'AU']);

    $res = $this->actingAs($me)->getJson('/api/v1/feeds/local')->assertOk();
    $ulids = collect($res->json('data'))->pluck('ulid');

    expect($ulids)->toContain($visible->ulid)
        ->not->toContain($followersOnly->ulid)
        ->not->toContain($draft->ulid)
        ->not->toContain($privatePost->ulid);
});

test('local feed returns 422 when the viewer has no country set', function () {
    $me = userWithProfile();

    $this->actingAs($me)->getJson('/api/v1/feeds/local')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Set your location first');
});

test('city-scope local feed returns 422 when the viewer has no city set', function () {
    $me = viewerInLocale('AU', null);

    $this->actingAs($me)->getJson('/api/v1/feeds/local?scope=city')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Set your location first');
});

test('local feed requires authentication', function () {
    $this->getJson('/api/v1/feeds/local')->assertUnauthorized();
});
