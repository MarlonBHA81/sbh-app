<?php

namespace App\Http\Controllers\Api\V1\Corporate;

use App\Models\Cohort;
use App\Models\Disbursement;
use App\Models\Profile;
use App\Models\Programme;
use App\Models\ProgrammeMilestone;
use App\Models\SupplierEnrolment;
use Illuminate\Http\Request;

/**
 * Shared authorization for the corporate self-serve portal. The active profile
 * (X-Profile-Id) must be a corporate the user can manage, and every programme,
 * cohort, enrolment, milestone or disbursement acted on must belong to that
 * corporate — Filament has no tenancy, and neither does this API, so isolation
 * is enforced explicitly on every request.
 */
trait InteractsWithActiveCorporate
{
    /** The active corporate profile, or a 4xx if the caller isn't running one. */
    protected function activeCorporate(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isCorporate(), 422, 'Switch to a corporate profile to use the ESD portal.');
        abort_unless($profile->canManageMembers($request->user()), 403, 'You cannot manage this corporate.');

        return $profile;
    }

    /** Ensure the programme belongs to the active corporate. */
    protected function authorizeProgramme(Request $request, Programme $programme): Programme
    {
        abort_unless($programme->profile_id === $this->activeCorporate($request)->id, 404);

        return $programme;
    }

    /** Ensure the cohort's programme belongs to the active corporate. */
    protected function authorizeCohort(Request $request, Cohort $cohort): Cohort
    {
        $this->authorizeProgramme($request, $cohort->programme);

        return $cohort;
    }

    /** Ensure the enrolment's programme belongs to the active corporate. */
    protected function authorizeEnrolment(Request $request, SupplierEnrolment $enrolment): SupplierEnrolment
    {
        $this->authorizeCohort($request, $enrolment->cohort);

        return $enrolment;
    }

    /** Ensure the milestone's programme belongs to the active corporate. */
    protected function authorizeMilestone(Request $request, ProgrammeMilestone $milestone): ProgrammeMilestone
    {
        $this->authorizeEnrolment($request, $milestone->enrolment);

        return $milestone;
    }

    /** Ensure the disbursement's programme belongs to the active corporate. */
    protected function authorizeDisbursement(Request $request, Disbursement $disbursement): Disbursement
    {
        $this->authorizeEnrolment($request, $disbursement->enrolment);

        return $disbursement;
    }
}
