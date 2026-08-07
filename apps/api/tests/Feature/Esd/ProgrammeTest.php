<?php

use App\Filament\Resources\Programmes\Pages\CreateProgramme;
use App\Models\Cohort;
use App\Models\Profile;
use App\Models\Programme;
use Livewire\Livewire;

test('a corporate profile can be created via the factory', function () {
    $corporate = Profile::factory()->corporate()->create();

    expect($corporate->kind)->toBe(Profile::KIND_CORPORATE)
        ->and($corporate->isCorporate())->toBeTrue();
});

test('the corporate scope only returns corporate profiles', function () {
    Profile::factory()->corporate()->create();
    Profile::factory()->business()->create();
    Profile::factory()->create();

    $corporates = Profile::query()->corporate()->get();

    expect($corporates)->toHaveCount(1)
        ->and($corporates->first()->isCorporate())->toBeTrue();
});

test('a programme belongs to its corporate sponsor and gets a ulid', function () {
    $corporate = Profile::factory()->corporate()->create();
    $programme = Programme::factory()->for($corporate, 'corporate')->create();

    expect($programme->ulid)->not->toBeNull()
        ->and($programme->getRouteKeyName())->toBe('ulid')
        ->and($programme->corporate->is($corporate))->toBeTrue()
        ->and($programme->type)->toBe(Programme::TYPE_SUPPLIER_DEVELOPMENT)
        ->and($programme->status)->toBe(Programme::STATUS_DRAFT);
});

test('a programme has many cohorts', function () {
    $programme = Programme::factory()->create();
    Cohort::factory()->count(2)->for($programme)->create();

    expect($programme->cohorts)->toHaveCount(2)
        ->and($programme->cohorts->first()->programme->is($programme))->toBeTrue();
});

test('the forCorporate scope isolates programmes by sponsor', function () {
    $corpA = Profile::factory()->corporate()->create();
    $corpB = Profile::factory()->corporate()->create();
    Programme::factory()->count(2)->for($corpA, 'corporate')->create();
    Programme::factory()->for($corpB, 'corporate')->create();

    expect(Programme::query()->forCorporate($corpA)->get())->toHaveCount(2)
        ->and(Programme::query()->forCorporate($corpB)->get())->toHaveCount(1);
});

test('a cohort belongs to its programme and gets a ulid', function () {
    $cohort = Cohort::factory()->create();

    expect($cohort->ulid)->not->toBeNull()
        ->and($cohort->getRouteKeyName())->toBe('ulid')
        ->and($cohort->programme)->not->toBeNull()
        ->and($cohort->status)->toBe(Cohort::STATUS_ACTIVE);
});

test('the ESD admin pages render for an admin', function () {
    $admin = adminWithProfile();

    $this->actingAs($admin)->get('/admin/programmes')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/programmes/create')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/cohorts')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/cohorts/create')->assertSuccessful();
});

test('an admin can create a programme from the panel and it stamps the creator', function () {
    $admin = adminWithProfile();
    $corporate = Profile::factory()->corporate()->create();

    Livewire::actingAs($admin)
        ->test(CreateProgramme::class)
        ->fillForm([
            'profile_id' => $corporate->id,
            'name' => 'Township Supplier Accelerator',
            'type' => Programme::TYPE_SUPPLIER_DEVELOPMENT,
            'status' => Programme::STATUS_ACTIVE,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $programme = Programme::sole();
    expect($programme->name)->toBe('Township Supplier Accelerator')
        ->and($programme->profile_id)->toBe($corporate->id)
        ->and($programme->status)->toBe(Programme::STATUS_ACTIVE)
        ->and($programme->created_by)->toBe($admin->id);
});
