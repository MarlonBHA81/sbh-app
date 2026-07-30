<?php

use App\Models\BugReport;

test('a member can report a bug and it lands open for triage', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/bug-reports', [
            'summary' => 'Checkout button does nothing',
            'details' => 'Tapping Pay on the product page shows a spinner forever.',
            'url' => 'https://app.test/shop/products/abc',
            'app_version' => '1.4.2',
        ])
        ->assertCreated()
        ->assertJsonPath('status', BugReport::STATUS_OPEN);

    $report = BugReport::sole();
    expect($report->user_id)->toBe($user->id)
        ->and($report->profile_id)->toBe($user->personalProfile->id)
        ->and($report->summary)->toBe('Checkout button does nothing')
        ->and($report->url)->toBe('https://app.test/shop/products/abc');
});

test('a bug report requires a summary', function () {
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/bug-reports', ['details' => 'no summary here'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('summary');
});

test('reporting a bug requires authentication', function () {
    $this->postJson('/api/v1/bug-reports', ['summary' => 'x'])
        ->assertUnauthorized();
});
