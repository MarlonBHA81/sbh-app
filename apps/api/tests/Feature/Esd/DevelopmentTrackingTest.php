<?php

use App\Models\ActivityLog;
use App\Models\Cohort;
use App\Models\Disbursement;
use App\Models\Profile;
use App\Models\Programme;
use App\Models\ProgrammeMilestone;
use App\Models\SupplierEnrolment;

test('a milestone belongs to its enrolment and gets a ulid', function () {
    $milestone = ProgrammeMilestone::factory()->create();

    expect($milestone->ulid)->not->toBeNull()
        ->and($milestone->getRouteKeyName())->toBe('ulid')
        ->and($milestone->status)->toBe(ProgrammeMilestone::STATUS_PENDING)
        ->and($milestone->enrolment)->toBeInstanceOf(SupplierEnrolment::class);
});

test('completing and reopening a milestone toggles state and logs it', function () {
    $milestone = ProgrammeMilestone::factory()->create();

    $milestone->markComplete();
    expect($milestone->fresh()->status)->toBe(ProgrammeMilestone::STATUS_COMPLETE)
        ->and($milestone->fresh()->completed_at)->not->toBeNull();

    $milestone->reopen();
    expect($milestone->fresh()->status)->toBe(ProgrammeMilestone::STATUS_PENDING)
        ->and($milestone->fresh()->completed_at)->toBeNull();

    expect(ActivityLog::query()->where('action', 'milestone.complete')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'milestone.reopen')->exists())->toBeTrue();
});

test('a disbursement is planned until it has a disbursed date', function () {
    $planned = Disbursement::factory()->create();
    $paid = Disbursement::factory()->paid()->create();

    expect($planned->isPaid())->toBeFalse()
        ->and($paid->isPaid())->toBeTrue();

    expect(Disbursement::query()->planned()->count())->toBe(1)
        ->and(Disbursement::query()->actual()->count())->toBe(1);
});

test('marking a disbursement paid sets the date and logs it', function () {
    $disbursement = Disbursement::factory()->create();

    $disbursement->markDisbursed();

    expect($disbursement->fresh()->disbursed_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', 'disbursement.paid')->exists())->toBeTrue();
});

test('enrolment rollups count milestones and split planned vs actual spend', function () {
    $enrolment = SupplierEnrolment::factory()->create();
    ProgrammeMilestone::factory()->count(2)->for($enrolment, 'enrolment')->create();
    ProgrammeMilestone::factory()->complete()->for($enrolment, 'enrolment')->create();
    Disbursement::factory()->for($enrolment, 'enrolment')->create(['amount_cents' => 100_000]);
    Disbursement::factory()->paid()->for($enrolment, 'enrolment')->create(['amount_cents' => 250_000]);

    expect($enrolment->milestoneProgress())->toBe(['total' => 3, 'complete' => 1])
        ->and($enrolment->plannedDisbursedCents())->toBe(100_000)
        ->and($enrolment->actualDisbursedCents())->toBe(250_000);
});

test('forCorporate isolates milestones and disbursements by sponsor', function () {
    $corpA = Profile::factory()->corporate()->create();
    $corpB = Profile::factory()->corporate()->create();

    $enrolmentA = enrolmentForCorporate($corpA);
    $enrolmentB = enrolmentForCorporate($corpB);
    ProgrammeMilestone::factory()->count(2)->for($enrolmentA, 'enrolment')->create();
    ProgrammeMilestone::factory()->for($enrolmentB, 'enrolment')->create();
    Disbursement::factory()->for($enrolmentA, 'enrolment')->create();
    Disbursement::factory()->count(3)->for($enrolmentB, 'enrolment')->create();

    expect(ProgrammeMilestone::query()->forCorporate($corpA)->count())->toBe(2)
        ->and(ProgrammeMilestone::query()->forCorporate($corpB)->count())->toBe(1)
        ->and(Disbursement::query()->forCorporate($corpA)->count())->toBe(1)
        ->and(Disbursement::query()->forCorporate($corpB)->count())->toBe(3);
});

test('the enrolment edit page renders its milestone and disbursement managers', function () {
    $admin = adminWithProfile();
    $enrolment = SupplierEnrolment::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/supplier-enrolments/{$enrolment->ulid}/edit")
        ->assertSuccessful();
});

/** Build a supplier enrolment whose programme is owned by the given corporate. */
function enrolmentForCorporate(Profile $corporate): SupplierEnrolment
{
    $programme = Programme::factory()->for($corporate, 'corporate')->create();
    $cohort = Cohort::factory()->for($programme)->create();

    return SupplierEnrolment::factory()->create(['cohort_id' => $cohort->id]);
}
