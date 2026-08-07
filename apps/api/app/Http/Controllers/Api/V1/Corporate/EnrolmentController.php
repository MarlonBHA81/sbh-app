<?php

namespace App\Http\Controllers\Api\V1\Corporate;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Profile;
use App\Models\SupplierEnrolment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Corporate self-serve: invite verified suppliers into a cohort and move their
 * enrolments through the review state machine.
 */
class EnrolmentController extends Controller
{
    use InteractsWithActiveCorporate;

    /** Invite a verified business into the cohort. */
    public function store(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorizeCohort($request, $cohort);

        $validated = $request->validate([
            'supplier' => ['required', 'string'],
        ]);

        $supplier = Profile::query()->where('ulid', $validated['supplier'])->first();

        abort_unless($supplier?->isBusiness() && $supplier->is_verified, 422, 'Only a verified business can be enrolled.');

        if ($cohort->enrolments()->where('profile_id', $supplier->id)->exists()) {
            throw ValidationException::withMessages(['supplier' => ['This supplier is already in the cohort.']]);
        }

        if ($cohort->isFull()) {
            throw ValidationException::withMessages(['cohort' => ['This cohort is full.']]);
        }

        $enrolment = $cohort->enrolments()->create([
            'profile_id' => $supplier->id,
            'status' => SupplierEnrolment::STATUS_INVITED,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($enrolment->load('supplier:id,name,handle'))], 201);
    }

    /** Move an enrolment through accept/activate/complete/reject. */
    public function transition(Request $request, SupplierEnrolment $enrolment): JsonResponse
    {
        $this->authorizeEnrolment($request, $enrolment);

        $validated = $request->validate([
            'action' => ['required', 'in:accept,activate,complete,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = $request->user();

        match ($validated['action']) {
            'accept' => $enrolment->accept($actor, $validated['note'] ?? null),
            'activate' => $enrolment->activate($actor),
            'complete' => $enrolment->complete($actor),
            'reject' => $enrolment->reject($actor, $validated['note'] ?? null),
        };

        return response()->json(['data' => $this->present($enrolment->fresh()->load('supplier:id,name,handle'))]);
    }

    /** @return array<string, mixed> */
    private function present(SupplierEnrolment $enrolment): array
    {
        return [
            'ulid' => $enrolment->ulid,
            'status' => $enrolment->status,
            'enrolled_at' => $enrolment->enrolled_at?->toIso8601String(),
            'supplier' => [
                'name' => $enrolment->supplier?->name,
                'handle' => $enrolment->supplier?->handle,
            ],
        ];
    }
}
