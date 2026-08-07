<?php

use App\Models\Cohort;
use App\Models\Disbursement;
use App\Models\Profile;
use App\Models\ProfileMember;
use App\Models\Programme;
use App\Models\ProgrammeMilestone;
use App\Models\SupplierEnrolment;
use App\Models\User;

/** A user running a corporate profile, plus that corporate. */
function corporateOperator(): array
{
    $user = User::factory()->create();
    $corporate = Profile::factory()->corporate()->for($user)->create();

    return [$user, $corporate];
}

/** Act as the given user with the given profile active. */
function asCorporate(User $user, Profile $corporate)
{
    return test()->actingAs($user)->withHeader('X-Profile-Id', $corporate->ulid);
}

test('a corporate sees only its own programmes', function () {
    [$user, $corporate] = corporateOperator();
    Programme::factory()->count(2)->for($corporate, 'corporate')->create();
    Programme::factory()->create(); // another sponsor's

    asCorporate($user, $corporate)
        ->getJson('/api/v1/corporate/programmes')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('a corporate can create a programme', function () {
    [$user, $corporate] = corporateOperator();

    asCorporate($user, $corporate)
        ->postJson('/api/v1/corporate/programmes', [
            'name' => 'Township Accelerator',
            'type' => Programme::TYPE_SUPPLIER_DEVELOPMENT,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', Programme::STATUS_DRAFT);

    expect(Programme::sole()->profile_id)->toBe($corporate->id)
        ->and(Programme::sole()->created_by)->toBe($user->id);
});

test('a non-corporate profile cannot use the portal', function () {
    $user = User::factory()->create();
    $business = Profile::factory()->business()->for($user)->create();

    test()->actingAs($user)->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/corporate/programmes')
        ->assertStatus(422);
});

test('a corporate cannot view another corporate programme', function () {
    [$user, $corporate] = corporateOperator();
    $others = Programme::factory()->create();

    asCorporate($user, $corporate)
        ->getJson("/api/v1/corporate/programmes/{$others->ulid}")
        ->assertNotFound();
});

test('a corporate can add a cohort and see its roster', function () {
    [$user, $corporate] = corporateOperator();
    $programme = Programme::factory()->for($corporate, 'corporate')->create();

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/programmes/{$programme->ulid}/cohorts", ['name' => 'Intake 1'])
        ->assertCreated();

    $cohort = Cohort::sole();
    SupplierEnrolment::factory()->active()->for($cohort)->create();

    asCorporate($user, $corporate)
        ->getJson("/api/v1/corporate/cohorts/{$cohort->ulid}")
        ->assertOk()
        ->assertJsonCount(1, 'data.roster');
});

test('a corporate can invite a verified supplier but not an unverified one', function () {
    [$user, $corporate] = corporateOperator();
    $cohort = Cohort::factory()->for(Programme::factory()->for($corporate, 'corporate'))->create();
    $verified = Profile::factory()->business()->create(['is_verified' => true]);
    $unverified = Profile::factory()->business()->create(['is_verified' => false]);

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/cohorts/{$cohort->ulid}/enrolments", ['supplier' => $verified->ulid])
        ->assertCreated()
        ->assertJsonPath('data.status', SupplierEnrolment::STATUS_INVITED);

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/cohorts/{$cohort->ulid}/enrolments", ['supplier' => $unverified->ulid])
        ->assertStatus(422);
});

test('a corporate can transition an enrolment through the state machine', function () {
    [$user, $corporate] = corporateOperator();
    $cohort = Cohort::factory()->for(Programme::factory()->for($corporate, 'corporate'))->create();
    $enrolment = SupplierEnrolment::factory()->for($cohort)->create();

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/enrolments/{$enrolment->ulid}/transition", ['action' => 'accept'])
        ->assertOk()
        ->assertJsonPath('data.status', SupplierEnrolment::STATUS_ACCEPTED);
});

test('a corporate cannot touch an enrolment in another corporate programme', function () {
    [$user, $corporate] = corporateOperator();
    $foreign = SupplierEnrolment::factory()->create();

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/enrolments/{$foreign->ulid}/transition", ['action' => 'accept'])
        ->assertNotFound();
});

test('a corporate can record milestones and disbursements', function () {
    [$user, $corporate] = corporateOperator();
    $cohort = Cohort::factory()->for(Programme::factory()->for($corporate, 'corporate'))->create();
    $enrolment = SupplierEnrolment::factory()->active()->for($cohort)->create();

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/enrolments/{$enrolment->ulid}/milestones", ['title' => 'Tax clearance'])
        ->assertCreated();
    $milestone = ProgrammeMilestone::sole();

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/milestones/{$milestone->ulid}/update", ['action' => 'complete'])
        ->assertOk()
        ->assertJsonPath('data.status', ProgrammeMilestone::STATUS_COMPLETE);

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/enrolments/{$enrolment->ulid}/disbursements", [
            'amount_cents' => 500_000,
            'kind' => Disbursement::KIND_GRANT,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_paid', false);
    $disbursement = Disbursement::sole();

    asCorporate($user, $corporate)
        ->postJson("/api/v1/corporate/disbursements/{$disbursement->ulid}/paid")
        ->assertOk()
        ->assertJsonPath('data.is_paid', true);
});

test('the programme report endpoint returns the summary and supplier rows', function () {
    [$user, $corporate] = corporateOperator();
    $programme = Programme::factory()->active()->for($corporate, 'corporate')->create();
    $cohort = Cohort::factory()->for($programme)->create();
    $enrolment = SupplierEnrolment::factory()->active()->for($cohort)->create();
    ProgrammeMilestone::factory()->complete()->for($enrolment, 'enrolment')->create();
    Disbursement::factory()->paid()->for($enrolment, 'enrolment')->create(['amount_cents' => 250_000]);

    asCorporate($user, $corporate)
        ->getJson("/api/v1/corporate/programmes/{$programme->ulid}/report")
        ->assertOk()
        ->assertJsonPath('data.summary.suppliers', 1)
        ->assertJsonPath('data.summary.disbursed.actual_cents', 250_000)
        ->assertJsonCount(1, 'data.suppliers');
});

test('the programme show endpoint embeds the rollup summary', function () {
    [$user, $corporate] = corporateOperator();
    $programme = Programme::factory()->for($corporate, 'corporate')->create();
    Cohort::factory()->count(2)->for($programme)->create();

    asCorporate($user, $corporate)
        ->getJson("/api/v1/corporate/programmes/{$programme->ulid}")
        ->assertOk()
        ->assertJsonPath('data.summary.cohorts', 2)
        ->assertJsonCount(2, 'data.cohorts');
});

test('a corporate cannot pull another corporate report', function () {
    [$user, $corporate] = corporateOperator();
    $foreign = Programme::factory()->create();

    asCorporate($user, $corporate)
        ->getJson("/api/v1/corporate/programmes/{$foreign->ulid}/report")
        ->assertNotFound();
});

test('a manager (not just the owner) can run the corporate', function () {
    [$owner, $corporate] = corporateOperator();
    $manager = User::factory()->create();
    $corporate->memberships()->create([
        'user_id' => $manager->id,
        'role' => ProfileMember::ROLE_MANAGER,
    ]);

    asCorporate($manager, $corporate)
        ->getJson('/api/v1/corporate/programmes')
        ->assertOk();
});
