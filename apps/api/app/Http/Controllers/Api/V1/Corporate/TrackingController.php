<?php

namespace App\Http\Controllers\Api\V1\Corporate;

use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\ProgrammeMilestone;
use App\Models\SupplierEnrolment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Corporate self-serve: development tracking entry — milestones and ED/SD
 * disbursements against a supplier enrolment.
 */
class TrackingController extends Controller
{
    use InteractsWithActiveCorporate;

    public function storeMilestone(Request $request, SupplierEnrolment $enrolment): JsonResponse
    {
        $this->authorizeEnrolment($request, $enrolment);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $milestone = $enrolment->milestones()->create($validated + ['status' => ProgrammeMilestone::STATUS_PENDING]);

        return response()->json(['data' => $this->presentMilestone($milestone)], 201);
    }

    public function updateMilestone(Request $request, ProgrammeMilestone $milestone): JsonResponse
    {
        $this->authorizeMilestone($request, $milestone);

        $validated = $request->validate([
            'action' => ['required', 'in:complete,reopen'],
        ]);

        $validated['action'] === 'complete'
            ? $milestone->markComplete($request->user())
            : $milestone->reopen($request->user());

        return response()->json(['data' => $this->presentMilestone($milestone->fresh())]);
    }

    public function storeDisbursement(Request $request, SupplierEnrolment $enrolment): JsonResponse
    {
        $this->authorizeEnrolment($request, $enrolment);

        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'kind' => ['required', 'in:'.implode(',', [Disbursement::KIND_GRANT, Disbursement::KIND_LOAN, Disbursement::KIND_IN_KIND])],
            'disbursed_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $disbursement = $enrolment->disbursements()->create($validated + [
            'currency' => $validated['currency'] ?? 'ZAR',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->presentDisbursement($disbursement)], 201);
    }

    public function markDisbursementPaid(Request $request, Disbursement $disbursement): JsonResponse
    {
        $this->authorizeDisbursement($request, $disbursement);

        $disbursement->markDisbursed($request->user());

        return response()->json(['data' => $this->presentDisbursement($disbursement->fresh())]);
    }

    /** @return array<string, mixed> */
    private function presentMilestone(ProgrammeMilestone $milestone): array
    {
        return [
            'ulid' => $milestone->ulid,
            'title' => $milestone->title,
            'status' => $milestone->status,
            'due_at' => $milestone->due_at?->toIso8601String(),
            'completed_at' => $milestone->completed_at?->toIso8601String(),
            'note' => $milestone->note,
        ];
    }

    /** @return array<string, mixed> */
    private function presentDisbursement(Disbursement $disbursement): array
    {
        return [
            'ulid' => $disbursement->ulid,
            'amount_cents' => $disbursement->amount_cents,
            'currency' => $disbursement->currency,
            'kind' => $disbursement->kind,
            'disbursed_at' => $disbursement->disbursed_at?->toIso8601String(),
            'is_paid' => $disbursement->isPaid(),
            'reference' => $disbursement->reference,
        ];
    }
}
