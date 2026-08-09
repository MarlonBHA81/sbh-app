<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use App\Support\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Member self-service TOTP two-factor management. Enrolment is a two-step
 * dance: enroll() hands back a secret + QR (stored unconfirmed), then
 * confirm() proves the user can generate a valid code before 2FA starts
 * gating their logins. Disabling / regenerating recovery codes requires a
 * fresh identity proof (password, or a current TOTP code for password-less
 * social accounts).
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /** Current 2FA state for the signed-in user. */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }

    /**
     * Begin enrolment: generate a secret and return it with a scannable QR.
     * The secret is stored unconfirmed; it does not gate login until confirm().
     */
    public function enroll(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => [__('Two-factor authentication is already enabled. Disable it first to re-enrol.')],
            ]);
        }

        $secret = $this->twoFactor->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'qr' => $this->twoFactor->qrCodeDataUri($user, $secret),
        ]);
    }

    /**
     * Finish enrolment: verify a code against the pending secret, mark it
     * confirmed and issue single-use recovery codes (shown exactly once).
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'code' => [__('Start a new two-factor setup first.')],
            ]);
        }

        if (! $this->twoFactor->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => [__('That code is incorrect or has expired.')],
            ]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        Activity::log('auth.2fa.enabled', actor: $user);

        return response()->json(['recovery_codes' => $codes]);
    }

    /** Re-issue recovery codes (invalidates the previous set). */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->assertEnabled($user);
        $this->assertIdentity($request, $user);

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        Activity::log('auth.2fa.recovery_regenerated', actor: $user);

        return response()->json(['recovery_codes' => $codes]);
    }

    /** Turn 2FA off entirely, wiping the secret and recovery codes. */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->assertEnabled($user);
        $this->assertIdentity($request, $user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Activity::log('auth.2fa.disabled', actor: $user);

        return response()->json(['message' => __('Two-factor authentication disabled.')]);
    }

    private function assertEnabled(User $user): void
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => [__('Two-factor authentication is not enabled.')],
            ]);
        }
    }

    /**
     * Re-confirm the caller's identity for a sensitive change. Accepts the
     * account password, or — for password-less (social) accounts — a current
     * TOTP code or recovery code.
     */
    private function assertIdentity(Request $request, User $user): void
    {
        $request->validate([
            'password' => ['sometimes', 'string'],
            'code' => ['sometimes', 'string'],
        ]);

        if ($user->password !== null && $request->filled('password')) {
            if (Hash::check($request->string('password'), $user->password)) {
                return;
            }

            throw ValidationException::withMessages([
                'password' => [__('That password is incorrect.')],
            ]);
        }

        if ($request->filled('code')) {
            $code = (string) $request->string('code');
            $codes = $user->two_factor_recovery_codes ?? [];

            if ($this->twoFactor->verify((string) $user->two_factor_secret, $code)
                || $this->twoFactor->consumeRecoveryCode($codes, $code) !== null) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'code' => [__('Confirm your identity to change two-factor settings.')],
        ]);
    }
}
