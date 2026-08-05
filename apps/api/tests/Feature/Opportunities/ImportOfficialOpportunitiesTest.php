<?php

use App\Models\Opportunity;

function writeOpportunitiesFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'opps').'.json';
    file_put_contents($path, json_encode($rows));

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/opps*.json') ?: [] as $f) {
        @unlink($f);
    }
});

test('the importer creates opportunities from a structured file', function () {
    $path = writeOpportunitiesFile([
        [
            'type' => 'tender',
            'title' => 'Fibre rollout tender',
            'description' => 'Quotations invited for a municipal fibre rollout.',
            'source_ref' => 'ETENDER-001',
            'organisation' => 'City Metro',
        ],
    ]);

    $this->artisan('opportunities:import', [
        'file' => $path,
        '--source' => 'eTenders',
        '--official' => true,
        '--publish' => true,
    ])->assertSuccessful();

    $opp = Opportunity::query()->where('source_ref', 'ETENDER-001')->first();
    expect($opp)->not->toBeNull();
    expect($opp->source)->toBe('eTenders');
    expect($opp->is_official)->toBeTrue();
    expect($opp->is_published)->toBeTrue();
    expect($opp->type)->toBe('tender');
});

test('re-importing the same ref updates instead of duplicating', function () {
    $make = fn (string $title) => writeOpportunitiesFile([[
        'title' => $title,
        'description' => 'Some description.',
        'source_ref' => 'DUP-1',
    ]]);

    $this->artisan('opportunities:import', ['file' => $make('First title'), '--source' => 'SEDA'])
        ->assertSuccessful();
    $this->artisan('opportunities:import', ['file' => $make('Updated title'), '--source' => 'SEDA'])
        ->assertSuccessful();

    expect(Opportunity::query()->where('source', 'SEDA')->count())->toBe(1);
    expect(Opportunity::query()->where('source', 'SEDA')->first()->title)->toBe('Updated title');
});

test('the importer skips rows missing a title or description', function () {
    $path = writeOpportunitiesFile([
        ['title' => 'No description', 'source_ref' => 'X'],
        ['title' => 'Good one', 'description' => 'Has both.', 'source_ref' => 'Y'],
    ]);

    $this->artisan('opportunities:import', ['file' => $path])->assertSuccessful();

    expect(Opportunity::query()->count())->toBe(1);
});

test('the importer fails cleanly on a missing file', function () {
    $this->artisan('opportunities:import', ['file' => '/no/such/file.json'])
        ->assertFailed();
});
