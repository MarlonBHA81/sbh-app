<?php

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

/** A currently-valid 6-digit TOTP code for a secret (file-local helper). */
function twoFactorOtp(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

/** Enrol + confirm 2FA for a user, returning [user, secret]. */
function enableTwoFactor(User $user): array
{
    $secret = app(TwoFactorService::class)->generateSecret();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user->fresh(), $secret];
}

test('enrolling returns a secret and QR without enabling 2FA yet', function () {
    $user = userWithProfile();

    $res = $this->actingAs($user)
        ->postJson('/api/v1/me/2fa/enroll')
        ->assertOk()
        ->assertJsonStructure(['secret', 'qr']);

    expect($res->json('qr'))->toStartWith('data:image/svg+xml;base64,');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();
});

test('confirming with a valid code enables 2FA and returns recovery codes', function () {
    $user = userWithProfile();
    $this->actingAs($user)->postJson('/api/v1/me/2fa/enroll')->assertOk();
    $secret = $user->fresh()->two_factor_secret;

    $res = $this->actingAs($user)
        ->postJson('/api/v1/me/2fa/confirm', ['code' => twoFactorOtp($secret)])
        ->assertOk()
        ->assertJsonStructure(['recovery_codes']);

    expect($res->json('recovery_codes'))->toHaveCount(8)
        ->and($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('confirming with a wrong code is rejected', function () {
    $user = userWithProfile();
    $this->actingAs($user)->postJson('/api/v1/me/2fa/enroll')->assertOk();

    $this->actingAs($user)
        ->postJson('/api/v1/me/2fa/confirm', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('the secret and recovery codes are encrypted at rest and hidden from JSON', function () {
    [$user, $secret] = enableTwoFactor(userWithProfile());

    $rawSecret = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
    expect($rawSecret)->not->toContain($secret)
        ->and(Crypt::decryptString($rawSecret))->toBe($secret);

    expect($user->toArray())
        ->not->toHaveKey('two_factor_secret')
        ->not->toHaveKey('two_factor_recovery_codes');
});

test('the /me user payload advertises 2FA state, not the secret', function () {
    [$user] = enableTwoFactor(userWithProfile());

    $this->actingAs($user)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.two_factor_enabled', true)
        ->assertJsonMissingPath('user.two_factor_secret');
});

test('login with 2FA enabled halts at a challenge instead of authenticating', function () {
    [$user] = enableTwoFactor(userWithProfile(userAttributes: [
        'email' => 'mfa@example.com',
        'password' => 'super-secret-password',
    ]));

    $this->postJson('/api/v1/auth/login', [
        'email' => 'mfa@example.com',
        'password' => 'super-secret-password',
    ])->assertOk()->assertJson(['two_factor' => true]);

    $this->assertGuest();
});

test('the challenge completes login with a valid code', function () {
    [$user, $secret] = enableTwoFactor(userWithProfile());

    $this->withSession(['auth.2fa' => ['id' => $user->id, 'at' => now()->timestamp]])
        ->postJson('/api/v1/auth/login/challenge', ['code' => twoFactorOtp($secret)])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

test('the challenge completes login with a recovery code and consumes it', function () {
    [$user] = enableTwoFactor(userWithProfile());

    $this->withSession(['auth.2fa' => ['id' => $user->id, 'at' => now()->timestamp]])
        ->postJson('/api/v1/auth/login/challenge', ['recovery_code' => 'AAAAA-BBBBB'])
        ->assertOk();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->two_factor_recovery_codes)
        ->not->toContain('AAAAA-BBBBB')
        ->toHaveCount(1);
});

test('the challenge rejects a wrong code', function () {
    [$user] = enableTwoFactor(userWithProfile());

    $this->withSession(['auth.2fa' => ['id' => $user->id, 'at' => now()->timestamp]])
        ->postJson('/api/v1/auth/login/challenge', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->assertGuest();
});

test('the challenge fails when the pending session has expired', function () {
    [$user, $secret] = enableTwoFactor(userWithProfile());

    $this->withSession(['auth.2fa' => ['id' => $user->id, 'at' => now()->subMinutes(10)->timestamp]])
        ->postJson('/api/v1/auth/login/challenge', ['code' => twoFactorOtp($secret)])
        ->assertUnprocessable();

    $this->assertGuest();
});

test('disabling 2FA requires the password and wipes the secret', function () {
    [$user] = enableTwoFactor(userWithProfile(userAttributes: [
        'password' => 'super-secret-password',
    ]));

    // Wrong password is refused.
    $this->actingAs($user)
        ->deleteJson('/api/v1/me/2fa', ['password' => 'nope'])
        ->assertUnprocessable();
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    // Correct password disables it.
    $this->actingAs($user)
        ->deleteJson('/api/v1/me/2fa', ['password' => 'super-secret-password'])
        ->assertOk();
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('regenerating recovery codes requires identity and replaces the set', function () {
    [$user] = enableTwoFactor(userWithProfile(userAttributes: [
        'password' => 'super-secret-password',
    ]));

    $res = $this->actingAs($user)
        ->postJson('/api/v1/me/2fa/recovery-codes', ['password' => 'super-secret-password'])
        ->assertOk()
        ->assertJsonStructure(['recovery_codes']);

    expect($res->json('recovery_codes'))->toHaveCount(8)
        ->and($user->fresh()->two_factor_recovery_codes)->not->toContain('AAAAA-BBBBB');
});
