<?php

use App\Models\Media;

test('a job listing is created with its fields', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'job',
        'payload' => [
            'title' => 'Senior Engineer',
            'company' => 'Acme',
            'location' => 'Cape Town',
            'employment_type' => 'full_time',
            'salary_min' => 50000,
            'salary_max' => 80000,
            'apply_url' => 'https://acme.example/apply',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'job')
        ->assertJsonPath('data.job.title', 'Senior Engineer')
        ->assertJsonPath('data.job.employment_type', 'full_time')
        ->assertJsonPath('data.job.currency', 'ZAR')
        ->assertJsonPath('data.job.is_expired', false);
});

test('a job requires a valid employment type', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'job',
        'payload' => ['title' => 'X', 'employment_type' => 'slavery'],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.employment_type');
});

test('a job requires a title', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'job',
        'payload' => ['employment_type' => 'contract'],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.title');
});

test('a job with a past expiry reports is_expired', function () {
    $user = userWithProfile();

    $ulid = $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'job',
        'payload' => [
            'title' => 'Old Role',
            'employment_type' => 'contract',
            'expires_at' => now()->subDay()->toISOString(),
        ],
    ])->json('data.ulid');

    $this->actingAs($user)->getJson("/api/v1/posts/{$ulid}")
        ->assertJsonPath('data.job.is_expired', true);
});

test('a portfolio requires between one and ten images', function () {
    $user = userWithProfile();

    // Zero media -> required.
    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'portfolio',
        'payload' => ['title' => 'My Work'],
    ])->assertUnprocessable()->assertJsonValidationErrors('media_ids');

    // Eleven media -> over the max.
    $eleven = Media::factory()->count(11)->create(['profile_id' => $user->personalProfile->id])
        ->pluck('ulid')->all();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'portfolio',
        'payload' => ['title' => 'My Work'],
        'media_ids' => $eleven,
    ])->assertUnprocessable()->assertJsonValidationErrors('media_ids');
});

test('a valid portfolio is created with images', function () {
    $user = userWithProfile();
    $media = Media::factory()->count(3)->create(['profile_id' => $user->personalProfile->id])
        ->pluck('ulid')->all();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'portfolio',
        'payload' => ['title' => 'My Work', 'description' => 'Selected pieces'],
        'media_ids' => $media,
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'portfolio')
        ->assertJsonPath('data.payload.title', 'My Work')
        ->assertJsonCount(3, 'data.media');
});

test('a portfolio rejects video media', function () {
    $user = userWithProfile();
    $video = Media::factory()->video()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'portfolio',
        'payload' => ['title' => 'My Work'],
        'media_ids' => [$video->ulid],
    ])->assertUnprocessable()->assertJsonValidationErrors('media_ids');
});
