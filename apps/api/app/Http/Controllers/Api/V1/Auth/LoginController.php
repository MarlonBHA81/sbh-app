<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use App\Support\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** How long a pending 2FA challenge stays valid after the password step. */
    private const CHALLENGE_TTL_SECONDS = 300;

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! $user->password || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->isBanned()) {
            return response()->json([
                'message' => __('Your account has been banned.'),
                'ban_reason' => $user->ban_reason,
            ], 403);
        }

        // Password is correct — but if the account has confirmed TOTP 2FA, we
        // stop here and require a code before completing login. The pending
        // user id lives in the (pre-auth) session so the follow-up challenge
        // request can complete it; it expires after CHALLENGE_TTL_SECONDS.
        if ($user->hasTwoFactorEnabled()) {
            if ($request->hasSession()) {
                $request->session()->put('auth.2fa', [
                    'id' => $user->id,
                    'at' => now()->timestamp,
                ]);
            }

            return response()->json(['two_factor' => true]);
        }

        return $this->completeLogin($request, $user);
    }

    /**
     * Second step of a two-factor login: verify a TOTP or recovery code against
     * the user pending in the session, then finish the login.
     */
    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['sometimes', 'string'],
            'recovery_code' => ['sometimes', 'string'],
        ]);

        $pending = $request->hasSession() ? $request->session()->get('auth.2fa') : null;

        if (! is_array($pending) || ! isset($pending['id'], $pending['at'])
            || (now()->timestamp - (int) $pending['at']) > self::CHALLENGE_TTL_SECONDS) {
            $request->session()?->forget('auth.2fa');

            throw ValidationException::withMessages([
                'code' => [__('Your login session expired. Please sign in again.')],
            ]);
        }

        $user = User::query()->find($pending['id']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()?->forget('auth.2fa');

            throw ValidationException::withMessages([
                'code' => [__('Your login session expired. Please sign in again.')],
            ]);
        }

        if ($user->isBanned()) {
            $request->session()?->forget('auth.2fa');

            return response()->json([
                'message' => __('Your account has been banned.'),
                'ban_reason' => $user->ban_reason,
            ], 403);
        }

        $secret = (string) $user->two_factor_secret;

        // A recovery code is single-use: on match, persist the shortened list.
        if ($request->filled('recovery_code')) {
            $remaining = $this->twoFactor->consumeRecoveryCode(
                $user->two_factor_recovery_codes ?? [],
                (string) $request->string('recovery_code'),
            );

            if ($remaining === null) {
                throw ValidationException::withMessages([
                    'recovery_code' => [__('That recovery code is invalid or already used.')],
                ]);
            }

            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
            Activity::log('auth.2fa.recovery_used', actor: $user);
        } elseif (! $this->twoFactor->verify($secret, (string) $request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => [__('That code is incorrect or has expired.')],
            ]);
        }

        $request->session()?->forget('auth.2fa');

        return $this->completeLogin($request, $user);
    }

    public function logout(Request $request): JsonResponse
    {
        Activity::log('auth.logout', actor: $request->user());

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('Logged out.')]);
    }

    /** Complete a successful login: establish the session and log it. */
    private function completeLogin(Request $request, User $user): JsonResponse
    {
        Auth::guard('web')->login($user, remember: true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        Activity::log('auth.login', actor: $user);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
