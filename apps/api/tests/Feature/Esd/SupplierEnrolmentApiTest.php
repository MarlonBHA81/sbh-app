<?php

use App\Models\Cohort;
use App\Models\Profile;
use App\Models\Programme;
use App\Models\SupplierEnrolment;
use App\Models\User;

/** A user acting as a verified supplier business, plus that business profile. */
function esdSupplier(bool $verified = true): array
{
    $user = User::factory()->create();
    $business = Profile::factory()->business()->for($user)->create(['is_verified' => $verified]);

    return [$user, $business];
}

/** An active cohort of an active programme, open for applications. */
function esdOpenCohort(): Cohort
{
    $programme = Programme::factory()->active()->create();

    return Cohort::factory()->for($programme)->create(['status' => Cohort::STATUS_ACTIVE]);
}

test('a verified business can apply to an open cohort', function () {
    [$user, $business] = esdSupplier();
    $cohort = esdOpenCohort();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/cohorts/{$cohort->ulid}/apply")
        ->assertCreated()
        ->assertJsonPath('data.status', SupplierEnrolment::STATUS_APPLIED);

    expect(SupplierEnrolment::query()
        ->where('cohort_id', $cohort->id)
        ->where('profile_id', $business->id)
        ->where('status', SupplierEnrolment::STATUS_APPLIED)
        ->exists())->toBeTrue();
});

test('an unverified business cannot apply', function () {
    [$user, $business] = esdSupplier(verified: false);
    $cohort = esdOpenCohort();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/cohorts/{$cohort->ulid}/apply")
        ->assertForbidden();
});

test('applying to a draft programme is rejected', function () {
    [$user, $business] = esdSupplier();
    $programme = Programme::factory()->create(['status' => Programme::STATUS_DRAFT]);
    $cohort = Cohort::factory()->for($programme)->create();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/cohorts/{$cohort->ulid}/apply")
        ->assertStatus(422);
});

test('a business cannot apply to the same cohort twice', function () {
    [$user, $business] = esdSupplier();
    $cohort = esdOpenCohort();
    SupplierEnrolment::factory()->applied()->create(['cohort_id' => $cohort->id, 'profile_id' => $business->id]);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/cohorts/{$cohort->ulid}/apply")
        ->assertStatus(422);
});

test('a business can accept an invite addressed to it', function () {
    [$user, $business] = esdSupplier();
    $invite = SupplierEnrolment::factory()->create(['profile_id' => $business->id]);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/me/enrolments/{$invite->ulid}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', SupplierEnrolment::STATUS_ACCEPTED);

    expect($invite->fresh()->enrolled_at)->not->toBeNull();
});

test('a business cannot accept an invite that belongs to another business', function () {
    [$user, $business] = esdSupplier();
    $otherInvite = SupplierEnrolment::factory()->create();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/me/enrolments/{$otherInvite->ulid}/accept")
        ->assertForbidden();

    expect($otherInvite->fresh()->status)->toBe(SupplierEnrolment::STATUS_INVITED);
});

test('a business can withdraw its enrolment and list its enrolments', function () {
    [$user, $business] = esdSupplier();
    $enrolment = SupplierEnrolment::factory()->active()->create(['profile_id' => $business->id]);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me/enrolments')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson("/api/v1/me/enrolments/{$enrolment->ulid}/withdraw")
        ->assertOk()
        ->assertJsonPath('data.status', SupplierEnrolment::STATUS_WITHDRAWN);
});

test('a personal profile cannot use the supplier enrolment endpoints', function () {
    $user = userWithProfile();
    $personal = $user->profiles()->first();
    $cohort = esdOpenCohort();

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $personal->ulid)
        ->postJson("/api/v1/cohorts/{$cohort->ulid}/apply")
        ->assertStatus(422);
});
