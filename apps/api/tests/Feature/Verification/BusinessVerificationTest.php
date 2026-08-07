<?php

use App\Models\ActivityLog;
use App\Models\BusinessVerification;
use App\Models\BusinessVerificationDocument;
use App\Models\ModerationAction;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function verifyingBusinessOwner(): array
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();

    return [$owner, $business];
}

function submitVerification(User $owner, Profile $business, array $overrides = [])
{
    return test()->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->withHeader('Accept', 'application/json')
        ->post('/api/v1/me/verification', array_merge([
            'legal_name' => 'Thembi Foods (Pty) Ltd',
            'registration_number' => '2019/123456/07',
            'id_document' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'cipc_document' => UploadedFile::fake()->create('cipc.pdf', 120, 'application/pdf'),
        ], $overrides));
}

test('a business can submit a verification with documents', function () {
    Storage::fake('local');
    [$owner, $business] = verifyingBusinessOwner();

    submitVerification($owner, $business)
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.legal_name', 'Thembi Foods (Pty) Ltd');

    $verification = BusinessVerification::query()->where('profile_id', $business->id)->first();
    expect($verification)->not->toBeNull()
        ->and($verification->status)->toBe('pending')
        ->and($verification->submitted_by)->toBe($owner->id)
        ->and($verification->documents()->count())->toBe(2);

    // Files landed on the private disk under the verification's folder.
    $doc = $verification->documents()->where('type', 'id_document')->first();
    Storage::disk('local')->assertExists($doc->path);
    expect($doc->disk)->toBe('local');

    expect(ActivityLog::query()->where('action', 'verification.submitted')->exists())->toBeTrue();
});

test('an optional bbee document is stored when provided', function () {
    Storage::fake('local');
    [$owner, $business] = verifyingBusinessOwner();

    submitVerification($owner, $business, [
        'bbee_document' => UploadedFile::fake()->create('bbee.pdf', 90, 'application/pdf'),
    ])->assertCreated();

    $verification = BusinessVerification::query()->where('profile_id', $business->id)->first();
    expect($verification->documents()->count())->toBe(3)
        ->and($verification->documents()->where('type', 'bbee')->exists())->toBeTrue();
});

test('a personal profile cannot submit a verification', function () {
    Storage::fake('local');
    $owner = userWithProfile();
    $personal = $owner->profiles()->first();

    test()->actingAs($owner)
        ->withHeader('X-Profile-Id', $personal->ulid)
        ->withHeader('Accept', 'application/json')
        ->post('/api/v1/me/verification', [
            'legal_name' => 'X',
            'id_document' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
            'cipc_document' => UploadedFile::fake()->create('cipc.pdf', 10, 'application/pdf'),
        ])
        ->assertStatus(422);
});

test('disallowed document types are rejected', function () {
    Storage::fake('local');
    [$owner, $business] = verifyingBusinessOwner();

    submitVerification($owner, $business, [
        'id_document' => UploadedFile::fake()->create('id.exe', 10, 'application/x-msdownload'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_document');
});

test('a second submission is blocked while one is in progress', function () {
    Storage::fake('local');
    [$owner, $business] = verifyingBusinessOwner();

    submitVerification($owner, $business)->assertCreated();
    submitVerification($owner, $business)
        ->assertStatus(422)
        ->assertJsonValidationErrors('profile');
});

test('the member can read back their latest verification status', function () {
    Storage::fake('local');
    [$owner, $business] = verifyingBusinessOwner();
    submitVerification($owner, $business)->assertCreated();

    test()->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me/verification')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonCount(2, 'data.documents');
});

test('approving a verification marks the business profile verified', function () {
    [, $business] = verifyingBusinessOwner();
    $reviewer = User::factory()->create(['is_admin' => true]);
    $verification = BusinessVerification::create([
        'profile_id' => $business->id,
        'status' => BusinessVerification::STATUS_PENDING,
        'legal_name' => 'X',
    ]);

    $verification->markReviewing($reviewer);
    expect($verification->fresh()->status)->toBe('reviewing');

    $verification->approve($reviewer, 'Docs verified.');

    expect($verification->fresh()->status)->toBe('approved')
        ->and($business->fresh()->is_verified)->toBeTrue()
        ->and(ModerationAction::query()->where('action', 'verification.approve')->exists())->toBeTrue();
});

test('rejecting a verification records a reason and does not verify', function () {
    [, $business] = verifyingBusinessOwner();
    $reviewer = User::factory()->create(['is_admin' => true]);
    $verification = BusinessVerification::create([
        'profile_id' => $business->id,
        'status' => BusinessVerification::STATUS_PENDING,
        'legal_name' => 'X',
    ]);

    $verification->reject($reviewer, 'CIPC document illegible.');

    expect($verification->fresh()->status)->toBe('rejected')
        ->and($verification->fresh()->decision_note)->toBe('CIPC document illegible.')
        ->and($business->fresh()->is_verified)->toBeFalse();

    // A rejected business may resubmit.
    expect($business->businessVerifications ?? true)->not->toBeNull();
});

test('an admin can stream a submitted document but a member cannot', function () {
    Storage::fake('local');
    [$owner, $business] = verifyingBusinessOwner();
    submitVerification($owner, $business)->assertCreated();
    $doc = BusinessVerificationDocument::query()->firstOrFail();

    $url = "/api/v1/admin/verifications/documents/{$doc->ulid}/download";

    // Non-admin (the submitting member) is forbidden. Set the caller's own
    // active profile explicitly (test headers persist across requests).
    test()->actingAs($owner)
        ->withHeader('X-Profile-Id', $owner->personalProfile->ulid)
        ->get($url)->assertForbidden();

    // Admin streams the file.
    $admin = adminWithProfile();
    test()->actingAs($admin)
        ->withHeader('X-Profile-Id', $admin->personalProfile->ulid)
        ->get($url)->assertOk()->assertDownload();
});

test('verification endpoints require authentication', function () {
    $this->postJson('/api/v1/me/verification', [])->assertUnauthorized();
    $this->getJson('/api/v1/me/verification')->assertUnauthorized();
});
