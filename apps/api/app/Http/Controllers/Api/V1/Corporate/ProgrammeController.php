<?php

namespace App\Http\Controllers\Api\V1\Corporate;

use App\Http\Controllers\Controller;
use App\Models\Programme;
use App\Services\Esd\ProgrammeReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Corporate self-serve: the programmes the active corporate sponsors. Backs the
 * portal dashboard and the create-programme flow.
 */
class ProgrammeController extends Controller
{
    use InteractsWithActiveCorporate;

    public function index(Request $request): JsonResponse
    {
        $corporate = $this->activeCorporate($request);

        $programmes = Programme::query()
            ->where('profile_id', $corporate->id)
            ->withCount('cohorts')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $programmes->map(fn (Programme $p) => $this->present($p))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $corporate = $this->activeCorporate($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:'.implode(',', [Programme::TYPE_SUPPLIER_DEVELOPMENT, Programme::TYPE_ENTERPRISE_DEVELOPMENT])],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'budget_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $programme = Programme::create($validated + [
            'profile_id' => $corporate->id,
            'status' => Programme::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($programme->loadCount('cohorts'))], 201);
    }

    public function show(Request $request, Programme $programme): JsonResponse
    {
        $this->authorizeProgramme($request, $programme);

        $programme->loadCount('cohorts')->load(['cohorts' => fn ($query) => $query->withCount('enrolments')]);

        return response()->json([
            'data' => $this->present($programme) + [
                'summary' => ProgrammeReport::for($programme)->summary(),
                'cohorts' => $programme->cohorts->map(fn ($cohort) => [
                    'ulid' => $cohort->ulid,
                    'name' => $cohort->name,
                    'status' => $cohort->status,
                    'capacity' => $cohort->capacity,
                    'enrolments_count' => (int) $cohort->enrolments_count,
                ])->values(),
            ],
        ]);
    }

    /** The programme's supplier-level tracking + spend report (JSON). */
    public function report(Request $request, Programme $programme): JsonResponse
    {
        $this->authorizeProgramme($request, $programme);

        $report = ProgrammeReport::for($programme);

        return response()->json([
            'data' => [
                'summary' => $report->summary(),
                'suppliers' => $report->supplierRows(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Programme $programme): array
    {
        return [
            'ulid' => $programme->ulid,
            'name' => $programme->name,
            'type' => $programme->type,
            'status' => $programme->status,
            'description' => $programme->description,
            'starts_at' => $programme->starts_at?->toDateString(),
            'ends_at' => $programme->ends_at?->toDateString(),
            'budget_cents' => $programme->budget_cents,
            'cohorts_count' => (int) ($programme->cohorts_count ?? 0),
        ];
    }
}
