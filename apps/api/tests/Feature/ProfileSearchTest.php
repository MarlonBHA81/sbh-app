<?php

test('profile search matches on a handle prefix', function () {
    $searcher = userWithProfile(['handle' => 'searcher', 'name' => 'Seeker Zero']);
    userWithProfile(['handle' => 'marlon', 'name' => 'Marlon Story']);
    userWithProfile(['handle' => 'other', 'name' => 'Nobody']);

    $this->actingAs($searcher)->getJson('/api/v1/search/profiles?q=marl')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.handle', 'marlon');
});

test('profile search matches on a name prefix', function () {
    $searcher = userWithProfile(['handle' => 'searcher', 'name' => 'Seeker Zero']);
    userWithProfile(['handle' => 'xyz', 'name' => 'Zebra Cakes']);

    $this->actingAs($searcher)->getJson('/api/v1/search/profiles?q=zeb')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.handle', 'xyz');
});

test('profile search caps results at eight', function () {
    $searcher = userWithProfile(['handle' => 'searcher', 'name' => 'Seeker Zero']);
    foreach (range(1, 10) as $i) {
        userWithProfile(['handle' => 'match'.$i]);
    }

    $this->actingAs($searcher)->getJson('/api/v1/search/profiles?q=match')
        ->assertOk()
        ->assertJsonCount(8, 'data');
});

test('profile search requires a query', function () {
    $searcher = userWithProfile(['handle' => 'searcher', 'name' => 'Seeker Zero']);

    $this->actingAs($searcher)->getJson('/api/v1/search/profiles')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

test('profile search requires authentication', function () {
    $this->getJson('/api/v1/search/profiles?q=a')->assertUnauthorized();
});
