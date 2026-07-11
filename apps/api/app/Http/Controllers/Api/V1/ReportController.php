<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function store(Request $request): JsonResponse
    {
        $reporter = $this->activeProfile($request);

        $data = $request->validate([
            'reportable_type' => ['required', 'string', 'in:post,comment,profile'],
            'reportable_ulid' => ['required', 'string'],
            'category' => ['required', 'string', 'in:'.implode(',', Report::CATEGORIES)],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        [$report, $created] = $this->reports->create($reporter, $data);

        return response()->json([
            'ulid' => $report->ulid,
            'status' => $report->status,
            'existing' => ! $created,
        ], $created ? 201 : 200);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');

        return $profile;
    }
}
