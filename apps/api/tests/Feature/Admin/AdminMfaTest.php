<?php

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

test('the admin panel registers app-authentication MFA', function () {
    $providers = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders();

    expect($providers)->toHaveCount(1)
        ->and(array_values($providers)[0])->toBeInstanceOf(AppAuthentication::class);
});

test('a user is a valid app-authentication holder', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(HasAppAuthentication::class)
        ->and($user)->toBeInstanceOf(HasAppAuthenticationRecovery::class)
        ->and($user->getAppAuthenticationHolderName())->toBe($user->email);
});

test('the TOTP secret and recovery codes round-trip and are encrypted at rest', function () {
    $user = User::factory()->create();

    $user->saveAppAuthenticationSecret('SECRETKEY123456');
    $user->saveAppAuthenticationRecoveryCodes(['code-a', 'code-b']);

    // Getter returns the decrypted values.
    expect($user->fresh()->getAppAuthenticationSecret())->toBe('SECRETKEY123456')
        ->and($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['code-a', 'code-b']);

    // Raw column is ciphertext — never plaintext at rest.
    $rawSecret = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');
    expect($rawSecret)->not->toContain('SECRETKEY123456')
        ->and(Crypt::decryptString($rawSecret))->toBe('SECRETKEY123456');
});

test('the MFA secret is hidden from array/JSON serialisation', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('SECRETKEY123456');

    expect($user->fresh()->toArray())
        ->not->toHaveKey('app_authentication_secret')
        ->not->toHaveKey('app_authentication_recovery_codes');
});

test('the admin login page still renders with MFA enabled', function () {
    $this->get('/admin/login')->assertSuccessful();
});
