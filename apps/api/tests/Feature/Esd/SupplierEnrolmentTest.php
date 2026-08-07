<?php

use App\Filament\Resources\SupplierEnrolments\Pages\CreateSupplierEnrolment;
use App\Models\ActivityLog;
use App\Models\Cohort;
use App\Models\Profile;
use App\Models\Programme;
use App\Models\SupplierEnrolment;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

test('an enrolment belongs to its cohort and supplier and gets a ulid', function () {
    $enrolment = SupplierEnrolment::factory()->create();

    expect($enrolment->ulid)->not->toBeNull()
        ->and($enrolment->getRouteKeyName())->toBe('ulid')
        ->and($enrolment->status)->toBe(SupplierEnrolment::STATUS_INVITED)
        ->and($enrolment->cohort)->toBeInstanceOf(Cohort::class)
        ->and($enrolment->supplier->isBusiness())->toBeTrue();
});

test('a supplier cannot be enrolled in the same cohort twice', function () {
    $cohort = Cohort::factory()->create();
    $supplier = Profile::factory()->business()->create();

    SupplierEnrolment::factory()->create(['cohort_id' => $cohort->id, 'profile_id' => $supplier->id]);

    expect(fn () => SupplierEnrolment::factory()->create([
        'cohort_id' => $cohort->id,
        'profile_id' => $supplier->id,
    ]))->toThrow(QueryException::class);
});

test('the state machine drives invited to completed and logs each transition', function () {
    $enrolment = SupplierEnrolment::factory()->create();

    $enrolment->accept();
    expect($enrolment->fresh()->status)->toBe(SupplierEnrolment::STATUS_ACCEPTED)
        ->and($enrolment->fresh()->enrolled_at)->not->toBeNull();

    $enrolment->activate();
    expect($enrolment->fresh()->status)->toBe(SupplierEnrolment::STATUS_ACTIVE);

    $enrolment->complete();
    expect($enrolment->fresh()->status)->toBe(SupplierEnrolment::STATUS_COMPLETED);

    expect(ActivityLog::query()->where('action', 'enrolment.accept')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'enrolment.activate')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'enrolment.complete')->exists())->toBeTrue();
});

test('rejecting stores the reason and withdrawing marks withdrawn', function () {
    $rejected = SupplierEnrolment::factory()->create();
    $rejected->reject(null, 'Not a fit this cycle.');
    expect($rejected->fresh()->status)->toBe(SupplierEnrolment::STATUS_REJECTED)
        ->and($rejected->fresh()->decision_note)->toBe('Not a fit this cycle.');

    $withdrawn = SupplierEnrolment::factory()->active()->create();
    $withdrawn->withdraw();
    expect($withdrawn->fresh()->status)->toBe(SupplierEnrolment::STATUS_WITHDRAWN);
});

test('forCorporate isolates enrolments by the owning sponsor', function () {
    $corpA = Profile::factory()->corporate()->create();
    $corpB = Profile::factory()->corporate()->create();
    $cohortA = Cohort::factory()->for(Programme::factory()->for($corpA, 'corporate'))->create();
    $cohortB = Cohort::factory()->for(Programme::factory()->for($corpB, 'corporate'))->create();
    SupplierEnrolment::factory()->count(2)->create(['cohort_id' => $cohortA->id]);
    SupplierEnrolment::factory()->create(['cohort_id' => $cohortB->id]);

    expect(SupplierEnrolment::query()->forCorporate($corpA)->count())->toBe(2)
        ->and(SupplierEnrolment::query()->forCorporate($corpB)->count())->toBe(1);
});

test('a cohort with capacity reports full once open seats are taken', function () {
    $cohort = Cohort::factory()->create(['capacity' => 1]);
    expect($cohort->isFull())->toBeFalse();

    SupplierEnrolment::factory()->active()->create(['cohort_id' => $cohort->id]);
    expect($cohort->fresh()->isFull())->toBeTrue();
});

test('the enrolment admin pages render for an admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/supplier-enrolments')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/supplier-enrolments/create')->assertSuccessful();
});

test('an admin can enrol a verified supplier into a cohort from the panel', function () {
    $admin = adminWithProfile();
    $cohort = Cohort::factory()->create();
    $supplier = Profile::factory()->business()->create(['is_verified' => true]);

    Livewire::actingAs($admin)
        ->test(CreateSupplierEnrolment::class)
        ->fillForm([
            'cohort_id' => $cohort->id,
            'profile_id' => $supplier->id,
            'status' => SupplierEnrolment::STATUS_INVITED,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $enrolment = SupplierEnrolment::sole();
    expect($enrolment->cohort_id)->toBe($cohort->id)
        ->and($enrolment->profile_id)->toBe($supplier->id)
        ->and($enrolment->status)->toBe(SupplierEnrolment::STATUS_INVITED)
        ->and($enrolment->created_by)->toBe($admin->id);
});
