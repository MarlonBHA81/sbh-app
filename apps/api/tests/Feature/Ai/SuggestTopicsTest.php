<?php

use App\Services\Ai\AiGateway;
use Tests\Support\FakeAiGateway;

test('suggest-topics returns slugs from the gateway', function () {
    app()->instance(AiGateway::class, new FakeAiGateway(
        enabled: true,
        topics: ['small-business', 'marketing', 'branding'],
    ));

    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts/suggest-topics', ['text' => 'growing my coffee shop'])
        ->assertOk()
        ->assertExactJson(['data' => ['slugs' => ['small-business', 'marketing', 'branding']]]);
});

test('suggest-topics returns an empty list when the gateway is disabled', function () {
    app()->instance(AiGateway::class, new FakeAiGateway(enabled: false));

    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts/suggest-topics', ['text' => 'anything'])
        ->assertOk()
        ->assertExactJson(['data' => ['slugs' => []]]);
});

test('suggest-topics requires text and authentication', function () {
    $this->postJson('/api/v1/posts/suggest-topics', ['text' => 'hi'])
        ->assertUnauthorized();

    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/posts/suggest-topics', [])
        ->assertUnprocessable();
});
