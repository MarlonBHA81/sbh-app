<?php

namespace App\Http\Controllers\Api\V1\Corporate;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Programme;
use App\Models\SupplierEnrolment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Corporate self-serve: cohorts within a programme and their supplier roster.
 */
class CohortController extends Controller
{
    use InteractsWithActiveCorporate;

    public function store(Request $request, Programme $programme): JsonResponse
    {
        $this->authorizeProgramme($request, $programme);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cohort = $programme->cohorts()->create($validated + ['status' => Cohort::STATUS_ACTIVE]);

        return response()->json(['data' => $this->presentCohort($cohort)], 201);
    }

    /** Cohort detail with its supplier roster. */
    public function show(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorizeCohort($request, $cohort);

        $cohort->load([
            'enrolments' => fn ($query) => $query->with('supplier:id,name,handle,is_verified')
                ->withCount([
                    'milestones as milestones_total',
                    'milestones as milestones_complete' => fn ($q) => $q->where('status', 'complete'),
                ])
                ->latest('id'),
        ]);

        return response()->json([
            'data' => $this->presentCohort($cohort) + [
                'roster' => $cohort->enrolments->map(fn (SupplierEnrolment $e) => [
                    'ulid' => $e->ulid,
                    'status' => $e->status,
                    'supplier' => [
                        'name' => $e->supplier?->name,
                        'handle' => $e->supplier?->handle,
                        'is_verified' => (bool) $e->supplier?->is_verified,
                    ],
                    'milestones_complete' => (int) $e->milestones_complete,
                    'milestones_total' => (int) $e->milestones_total,
                    'planned_cents' => $e->plannedDisbursedCents(),
                    'actual_cents' => $e->actualDisbursedCents(),
                ])->values(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentCohort(Cohort $cohort): array
    {
        return [
            'ulid' => $cohort->ulid,
            'name' => $cohort->name,
            'status' => $cohort->status,
            'capacity' => $cohort->capacity,
            'is_full' => $cohort->isFull(),
            'starts_at' => $cohort->starts_at?->toDateString(),
            'ends_at' => $cohort->ends_at?->toDateString(),
        ];
    }
}
