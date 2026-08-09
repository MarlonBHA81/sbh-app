<?php

use App\Contracts\CipcVerifier;
use App\Models\BusinessVerification;
use App\Models\Profile;
use App\Models\XpAction;
use App\Models\XpLedgerEntry;
use App\Services\Business\CipcResult;
use App\Services\Business\HttpCipcVerifier;
use App\Services\Business\NullCipcVerifier;
use App\Services\Business\StubCipcVerifier;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A pending verification for a fresh business, with a registration number. */
function cipcPendingVerification(string $registration = '2019/123456/07'): BusinessVerification
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();

    return BusinessVerification::create([
        'profile_id' => $business->id,
        'status' => BusinessVerification::STATUS_PENDING,
        'legal_name' => 'Acme (Pty) Ltd',
        'registration_number' => $registration,
        'submitted_by' => $owner->id,
    ]);
}

/** Seed the XP action so award() has something to write. */
function seedCipcXpAction(): void
{
    XpAction::query()->updateOrCreate(
        ['key' => GamificationService::BUSINESS_CIPC_VERIFIED],
        ['label' => 'Business confirmed on CIPC', 'points' => 50, 'daily_cap' => null],
    );
}

test('the stub verifier confirms well-formed registration numbers only', function () {
    $verifier = new StubCipcVerifier;

    expect($verifier->lookup('2019/123456/07')->isVerified())->toBeTrue()
        ->and($verifier->lookup('not-a-number')->status)->toBe(CipcResult::NOT_FOUND);
});

test('the null verifier never confirms', function () {
    expect((new NullCipcVerifier)->lookup('2019/123456/07')->isUnavailable())->toBeTrue();
});

test('the container resolves the verifier from config', function () {
    config()->set('services.cipc.enabled', false);
    expect(app(CipcVerifier::class))->toBeInstanceOf(NullCipcVerifier::class);

    config()->set('services.cipc.enabled', true);
    config()->set('services.cipc.driver', 'stub');
    expect(app(CipcVerifier::class))->toBeInstanceOf(StubCipcVerifier::class);

    config()->set('services.cipc.driver', 'http');
    config()->set('services.cipc.base_url', 'https://cipc.example');
    expect(app(CipcVerifier::class))->toBeInstanceOf(HttpCipcVerifier::class);
});

test('running a CIPC check on a valid registration stamps the sticker and awards XP', function () {
    seedCipcXpAction();
    $verification = cipcPendingVerification();

    $result = $verification->runCipcCheck(new StubCipcVerifier);

    expect($result->isVerified())->toBeTrue()
        ->and($verification->fresh()->cipc_status)->toBe('verified')
        ->and($verification->fresh()->cipc_registered_name)->not->toBeNull()
        ->and($verification->profile->fresh()->cipc_verified_at)->not->toBeNull()
        ->and(XpLedgerEntry::query()->where('action_key', GamificationService::BUSINESS_CIPC_VERIFIED)->count())->toBe(1);
});

test('a check without a registration number is unavailable and grants nothing', function () {
    $verification = cipcPendingVerification('');

    $result = $verification->runCipcCheck(new StubCipcVerifier);

    expect($result->isUnavailable())->toBeTrue()
        ->and($verification->profile->fresh()->cipc_verified_at)->toBeNull();
});

test('the sticker and XP are granted only once across re-checks', function () {
    seedCipcXpAction();
    $verification = cipcPendingVerification();

    $verification->runCipcCheck(new StubCipcVerifier);
    $stampedAt = $verification->profile->fresh()->cipc_verified_at;

    $verification->runCipcCheck(new StubCipcVerifier);

    expect($verification->profile->fresh()->cipc_verified_at->equalTo($stampedAt))->toBeTrue()
        ->and(XpLedgerEntry::query()->where('action_key', GamificationService::BUSINESS_CIPC_VERIFIED)->count())->toBe(1);
});

test('submitting a verification auto-runs the CIPC check when enabled', function () {
    Storage::fake('local');
    seedCipcXpAction();
    config()->set('services.cipc.enabled', true);
    config()->set('services.cipc.driver', 'stub');

    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();

    test()->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->withHeader('Accept', 'application/json')
        ->post('/api/v1/me/verification', [
            'legal_name' => 'Acme (Pty) Ltd',
            'registration_number' => '2019/123456/07',
            'id_document' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'cipc_document' => UploadedFile::fake()->create('cipc.pdf', 120, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.cipc_status', 'verified')
        ->assertJsonPath('data.cipc_verified', true);

    expect($business->fresh()->cipc_verified_at)->not->toBeNull();
});

test('the disabled default never grants a sticker on submission', function () {
    Storage::fake('local');
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();

    test()->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->withHeader('Accept', 'application/json')
        ->post('/api/v1/me/verification', [
            'legal_name' => 'Acme (Pty) Ltd',
            'registration_number' => '2019/123456/07',
            'id_document' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'cipc_document' => UploadedFile::fake()->create('cipc.pdf', 120, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.cipc_verified', false);

    expect($business->fresh()->cipc_verified_at)->toBeNull();
});

test('the me payload exposes the cipc_verified sticker flag', function () {
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create(['cipc_verified_at' => now()]);

    test()->actingAs($owner)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('active_profile.cipc_verified', true);
});
