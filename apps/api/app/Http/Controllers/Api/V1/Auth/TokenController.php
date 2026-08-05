<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function __invoke(TokenRequest $request): JsonResponse
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

        // Stamp the configured expiry window onto the token itself (Sanctum only
        // persists expires_at when passed explicitly). This makes the expiry
        // visible in the token-management endpoints and enforced per token, on
        // top of the global sanctum.expiration guard.
        $minutes = (int) config('sanctum.expiration');
        $expiresAt = $minutes > 0 ? now()->addMinutes($minutes) : null;

        return response()->json([
            'token' => $user->createToken($request->string('device_name'), ['*'], $expiresAt)->plainTextToken,
        ], 201);
    }
}
