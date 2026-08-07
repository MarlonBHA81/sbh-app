<?php

use App\Models\Cohort;
use App\Models\Disbursement;
use App\Models\Programme;
use App\Models\ProgrammeMilestone;
use App\Models\SupplierEnrolment;
use App\Services\Esd\ProgrammeReport;

/** A programme with one cohort, an active + an applied supplier, milestones and spend. */
function seededProgramme(): Programme
{
    $programme = Programme::factory()->active()->create();
    $cohort = Cohort::factory()->for($programme)->create(['name' => 'Alpha']);

    $active = SupplierEnrolment::factory()->active()->for($cohort)->create();
    SupplierEnrolment::factory()->applied()->for($cohort)->create();

    ProgrammeMilestone::factory()->count(2)->for($active, 'enrolment')->create();
    ProgrammeMilestone::factory()->complete()->for($active, 'enrolment')->create();

    Disbursement::factory()->for($active, 'enrolment')->create(['amount_cents' => 100_000]);
    Disbursement::factory()->paid()->for($active, 'enrolment')->create(['amount_cents' => 250_000]);

    return $programme;
}

test('the report summary rolls up status, milestones and planned vs actual spend', function () {
    $summary = ProgrammeReport::for(seededProgramme())->summary();

    expect($summary['cohorts'])->toBe(1)
        ->and($summary['suppliers'])->toBe(2)
        ->and($summary['supplier_status'])->toBe(['active' => 1, 'applied' => 1])
        ->and($summary['milestones'])->toBe(['total' => 3, 'complete' => 1])
        ->and($summary['disbursed'])->toBe(['planned_cents' => 100_000, 'actual_cents' => 250_000]);
});

test('the report has one row per supplier with its progress and spend', function () {
    $rows = ProgrammeReport::for(seededProgramme())->supplierRows();

    expect($rows)->toHaveCount(2);

    $active = collect($rows)->firstWhere('status', SupplierEnrolment::STATUS_ACTIVE);
    expect($active['cohort'])->toBe('Alpha')
        ->and($active['milestones_complete'])->toBe(1)
        ->and($active['milestones_total'])->toBe(3)
        ->and($active['planned_cents'])->toBe(100_000)
        ->and($active['actual_cents'])->toBe(250_000);
});

test('the CSV has a header and a row per supplier with rand-formatted amounts', function () {
    $csv = ProgrammeReport::for(seededProgramme())->toCsv();
    $lines = array_values(array_filter(explode("\n", trim($csv))));

    expect($lines)->toHaveCount(3) // header + 2 suppliers
        ->and($lines[0])->toContain('Cohort', 'Supplier', 'Planned (ZAR)', 'Disbursed (ZAR)')
        ->and($csv)->toContain('1000.00')
        ->and($csv)->toContain('2500.00');
});

test('a report only covers its own programme (corporate isolation)', function () {
    $mine = seededProgramme();
    seededProgramme(); // another sponsor's programme with its own suppliers

    // The report scoped to $mine sees only its two suppliers, not the other's.
    expect(ProgrammeReport::for($mine)->summary()['suppliers'])->toBe(2)
        ->and(ProgrammeReport::for($mine)->supplierRows())->toHaveCount(2);
});

test('an empty programme reports zeroed rollups and a header-only CSV', function () {
    $programme = Programme::factory()->create();

    $summary = ProgrammeReport::for($programme)->summary();
    expect($summary['suppliers'])->toBe(0)
        ->and($summary['supplier_status'])->toBe([])
        ->and($summary['milestones'])->toBe(['total' => 0, 'complete' => 0])
        ->and($summary['disbursed'])->toBe(['planned_cents' => 0, 'actual_cents' => 0]);

    $lines = array_values(array_filter(explode("\n", trim(ProgrammeReport::for($programme)->toCsv()))));
    expect($lines)->toHaveCount(1);
});

test('the programme list page renders with the export action for an admin', function () {
    $admin = adminWithProfile();
    Programme::factory()->create();

    $this->actingAs($admin)->get('/admin/programmes')->assertSuccessful();
});

test('the enrolments hasManyThrough spans a programmes cohorts', function () {
    $programme = Programme::factory()->create();
    $cohortA = Cohort::factory()->for($programme)->create();
    $cohortB = Cohort::factory()->for($programme)->create();
    SupplierEnrolment::factory()->count(2)->for($cohortA)->create();
    SupplierEnrolment::factory()->for($cohortB)->create();

    expect($programme->enrolments()->count())->toBe(3);
});
