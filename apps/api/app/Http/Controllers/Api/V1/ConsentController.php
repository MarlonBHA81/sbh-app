<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Support\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side cookie/privacy consent (POPIA/GDPR). The web banner still gates
 * cookies client-side; this persists an auditable record of each choice tied to
 * the authenticated data subject, the policy version, and the request context.
 */
class ConsentController extends Controller
{
    /** The current data subject's latest recorded consent (null if none). */
    public function show(Request $request): JsonResponse
    {
        $latest = ConsentRecord::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'cookie')
            ->latest('id')
            ->first();

        return response()->json([
            'data' => $latest === null ? null : [
                'choice' => $latest->choice,
                'policy_version' => $latest->policy_version,
                'recorded_at' => $latest->created_at?->toIso8601String(),
                'current_policy_version' => (string) config('privacy.policy_version'),
            ],
        ]);
    }

    /** Record a consent decision. Appends a new immutable row each time. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'choice' => ['required', 'in:accepted,rejected'],
            'categories' => ['sometimes', 'array'],
        ]);

        $record = ConsentRecord::create([
            'user_id' => $request->user()->id,
            'type' => 'cookie',
            'policy_version' => (string) config('privacy.policy_version'),
            'choice' => $validated['choice'],
            'categories' => $validated['categories'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);

        Activity::log('consent.recorded', $record, ['choice' => $record->choice]);

        return response()->json([
            'data' => [
                'choice' => $record->choice,
                'policy_version' => $record->policy_version,
                'recorded_at' => $record->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
