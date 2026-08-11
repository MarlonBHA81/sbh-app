<?php

use App\Contracts\CipcVerifier;
use App\Models\XpAction;
use App\Services\Business\CipcResult;
use App\Services\Gamification\GamificationService;

/**
 * The hard CIPC gate on business-profile creation: POST /api/v1/me/profiles
 * only ever mints a business profile once CIPC confirms its registration
 * number. Everything else (missing/malformed number, CIPC unavailable) is a
 * validation error and creates nothing.
 */
function seedBusinessCipcXpAction(): void
{
    XpAction::query()->updateOrCreate(
        ['key' => GamificationService::BUSINESS_CIPC_VERIFIED],
        ['label' => 'CIPC', 'points' => 50, 'daily_cap' => null],
    );
}

function enableStubCipc(): void
{
    config()->set('services.cipc.enabled', true);
    config()->set('services.cipc.driver', 'stub');
}

test('a business profile is created when CIPC verifies the registration number', function () {
    enableStubCipc();
    seedBusinessCipcXpAction();

    $user = userWithProfile();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/me/profiles', [
            'name' => 'Braai Spot',
            'category' => 'restaurant',
            'registration_number' => '2019/123456/07',
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'business')
        ->assertJsonPath('data.cipc_verified', true)
        ->assertJsonPath('data.registration_number', '2019/123456/07')
        ->assertJsonPath('data.cipc_registered_name', 'Registered Company 2019/123456/07');

    $profile = $user->businessProfiles()->firstOrFail();

    expect($profile->cipc_verified_at)->not->toBeNull()
        ->and($profile->cipc_registered_name)->toBe('Registered Company 2019/123456/07')
        ->and($profile->xp_total)->toBe(50);
});

test('creating a business profile without a registration number is rejected', function () {
    enableStubCipc();

    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/profiles', [
            'name' => 'No Number Co',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('registration_number');

    expect($user->businessProfiles()->count())->toBe(0);
});

test('creating a business profile with a malformed registration number is rejected', function () {
    enableStubCipc();

    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/profiles', [
            'name' => 'Bad Number Co',
            'registration_number' => '123-not-valid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('registration_number');

    expect($user->businessProfiles()->count())->toBe(0);
});

test('the gate blocks creation when CIPC is unavailable', function () {
    // Default config: CIPC disabled -> NullCipcVerifier -> unavailable.
    $user = userWithProfile();

    $this->actingAs($user)
        ->postJson('/api/v1/me/profiles', [
            'name' => 'Unverifiable Co',
            'registration_number' => '2019/123456/07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'registration_number' => 'Business verification via CIPC is currently unavailable. Please try again later.',
        ]);

    expect($user->businessProfiles()->count())->toBe(0);
});

test('a registration number not found on CIPC is rejected with the not-found message', function () {
    enableStubCipc();

    $user = userWithProfile();

    // Well-formed but the stub only verifies YYYY/NNNNNN/NN; force a not_found
    // by swapping to a verifier that never confirms.
    $this->app->bind(CipcVerifier::class, fn () => new class implements CipcVerifier
    {
        public function lookup(string $registrationNumber): CipcResult
        {
            return CipcResult::notFound();
        }
    });

    $this->actingAs($user)
        ->postJson('/api/v1/me/profiles', [
            'name' => 'Ghost Co',
            'registration_number' => '2019/123456/07',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'registration_number' => "That registration number wasn't found on CIPC.",
        ]);

    expect($user->businessProfiles()->count())->toBe(0);
});

test('personal profile creation is unaffected and needs no registration number', function () {
    $user = userWithProfile();

    $personal = $user->personalProfile;

    expect($personal)->not->toBeNull()
        ->and($personal->kind)->toBe('personal')
        ->and($personal->registration_number)->toBeNull()
        ->and($personal->cipc_verified_at)->toBeNull();
});
