<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Profile;
use App\Models\Programme;
use App\Models\SupplierEnrolment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Member-facing supplier enrolment: a verified business acts on invites the
 * corporate sent it, and applies to open cohorts. All actions run against the
 * active business profile resolved from the X-Profile-Id header.
 */
class SupplierEnrolmentController extends Controller
{
    /** The active business profile's enrolments (invites + applications). */
    public function index(Request $request): JsonResponse
    {
        $profile = $this->businessProfile($request);

        $enrolments = SupplierEnrolment::query()
            ->where('profile_id', $profile->id)
            ->with(['cohort.programme'])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $enrolments->map(fn (SupplierEnrolment $e) => $this->present($e))->values(),
        ]);
    }

    /** Apply to an open cohort of an active programme. */
    public function apply(Request $request, Cohort $cohort): JsonResponse
    {
        $profile = $this->businessProfile($request);

        abort_unless($profile->is_verified, 403, 'Only verified businesses can apply to a programme.');

        $cohort->loadMissing('programme');

        if ($cohort->programme->status !== Programme::STATUS_ACTIVE || $cohort->status !== Cohort::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['cohort' => ['This programme is not open for applications.']]);
        }

        if ($cohort->isFull()) {
            throw ValidationException::withMessages(['cohort' => ['This cohort is full.']]);
        }

        $existing = SupplierEnrolment::query()
            ->where('cohort_id', $cohort->id)
            ->where('profile_id', $profile->id)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['cohort' => ['You already have an enrolment in this cohort.']]);
        }

        $enrolment = SupplierEnrolment::create([
            'cohort_id' => $cohort->id,
            'profile_id' => $profile->id,
            'status' => SupplierEnrolment::STATUS_APPLIED,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($enrolment->load('cohort.programme'))], 201);
    }

    /** Accept an invite the corporate extended to this business. */
    public function accept(Request $request, SupplierEnrolment $enrolment): JsonResponse
    {
        $this->ownedEnrolment($request, $enrolment);

        abort_unless($enrolment->status === SupplierEnrolment::STATUS_INVITED, 422, 'This invite can no longer be accepted.');

        $enrolment->accept($request->user());

        return response()->json(['data' => $this->present($enrolment->load('cohort.programme'))]);
    }

    /** Withdraw from an invite/application/active enrolment. */
    public function withdraw(Request $request, SupplierEnrolment $enrolment): JsonResponse
    {
        $this->ownedEnrolment($request, $enrolment);

        $withdrawable = [
            SupplierEnrolment::STATUS_INVITED,
            SupplierEnrolment::STATUS_APPLIED,
            SupplierEnrolment::STATUS_ACCEPTED,
            SupplierEnrolment::STATUS_ACTIVE,
        ];

        abort_unless(in_array($enrolment->status, $withdrawable, true), 422, 'This enrolment cannot be withdrawn.');

        $enrolment->withdraw($request->user());

        return response()->json(['data' => $this->present($enrolment->load('cohort.programme'))]);
    }

    private function businessProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness(), 422, 'Only a business profile can enrol as a supplier.');

        return $profile;
    }

    /** Ensure the enrolment belongs to the acting business profile. */
    private function ownedEnrolment(Request $request, SupplierEnrolment $enrolment): void
    {
        $profile = $this->businessProfile($request);

        abort_unless($enrolment->profile_id === $profile->id, 403, 'This enrolment does not belong to your business.');
    }

    /** @return array<string, mixed> */
    private function present(SupplierEnrolment $enrolment): array
    {
        return [
            'ulid' => $enrolment->ulid,
            'status' => $enrolment->status,
            'enrolled_at' => $enrolment->enrolled_at?->toIso8601String(),
            'decision_note' => $enrolment->decision_note,
            'cohort' => [
                'ulid' => $enrolment->cohort->ulid,
                'name' => $enrolment->cohort->name,
            ],
            'programme' => [
                'ulid' => $enrolment->cohort->programme->ulid,
                'name' => $enrolment->cohort->programme->name,
                'type' => $enrolment->cohort->programme->type,
            ],
        ];
    }
}
