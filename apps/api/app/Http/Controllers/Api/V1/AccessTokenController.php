<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service management of the bearer tokens issued by POST /auth/token.
 *
 * Without this, a leaked token could only be cleared by an admin in Filament or
 * by deleting the whole account — the owner had no way to see their own active
 * sessions, let alone revoke one. Device/session management is the other half
 * of giving tokens a finite lifetime.
 */
class AccessTokenController extends Controller
{
    /** The caller's active tokens, most recently used first. */
    public function index(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        $tokens = $request->user()->tokens()
            ->orderByRaw('last_used_at IS NULL, last_used_at DESC')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
                // Lets a client label "this device" without guessing.
                'current' => $current !== null && $current->id === $token->id,
            ]);

        return response()->json(['data' => $tokens]);
    }

    /** Revoke one token. Scoped to the caller's own tokens — no cross-user IDs. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($id)->delete();

        abort_if($deleted === 0, 404, 'Token not found.');

        return response()->json(['data' => ['revoked' => true]]);
    }

    /**
     * Revoke every token. The "I lost my phone" button — deliberately does not
     * spare the current one, so a compromised device cannot keep its access by
     * being the one that made the call.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->delete();

        return response()->json(['data' => ['revoked' => $count]]);
    }
}
