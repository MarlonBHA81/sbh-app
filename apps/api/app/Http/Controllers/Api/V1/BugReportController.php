<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BugReportController extends Controller
{
    /**
     * A member reports a bug they hit. The active profile (if any) and the
     * current URL / user agent are captured to help triage, but only summary
     * is required so reporting stays low-friction.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'summary' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'string', 'max:2000'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $profile = $request->attributes->get('activeProfile');

        $report = BugReport::create([
            'user_id' => $request->user()->id,
            'profile_id' => $profile instanceof Profile ? $profile->id : null,
            'summary' => $data['summary'],
            'details' => $data['details'] ?? null,
            'url' => $data['url'] ?? null,
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'app_version' => $data['app_version'] ?? null,
        ]);

        return response()->json([
            'ulid' => $report->ulid,
            'status' => $report->status,
        ], 201);
    }
}
