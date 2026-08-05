<?php

use App\Filament\Imports\OpportunityImporter;
use App\Models\Opportunity;
use Filament\Actions\Imports\Models\Import;

/** Drive the importer over one CSV row exactly as Filament's import job does. */
function importOpportunityRow(array $row): void
{
    $admin = adminWithProfile();

    $import = new Import;
    $import->user_id = $admin->id;
    $import->file_name = 'opps.csv';
    $import->file_path = 'imports/opps.csv';
    $import->importer = OpportunityImporter::class;
    $import->total_rows = 1;
    $import->save();

    $columns = array_keys($row);
    $columnMap = array_combine($columns, $columns);

    $importer = new OpportunityImporter($import, $columnMap, []);
    $importer($row);
}

test('a CSV row creates an opportunity', function () {
    importOpportunityRow([
        'type' => 'grant',
        'title' => 'Youth Innovation Grant',
        'description' => 'Up to R500k for youth-led ventures.',
        'source' => 'CSV',
        'source_ref' => 'GRANT-001',
        'is_published' => '1',
    ]);

    $opp = Opportunity::sole();
    expect($opp->title)->toBe('Youth Innovation Grant')
        ->and($opp->type)->toBe('grant')
        ->and($opp->is_published)->toBeTrue();
});

test('an unknown type falls back to programme', function () {
    importOpportunityRow([
        'type' => 'not-a-real-type',
        'title' => 'Mystery Opportunity',
        'source' => 'CSV',
        'source_ref' => 'X1',
    ]);

    expect(Opportunity::sole()->type)->toBe('programme');
});

test('re-importing the same source_ref updates rather than duplicates', function () {
    importOpportunityRow([
        'type' => 'tender',
        'title' => 'Original title',
        'source' => 'CSV',
        'source_ref' => 'DUP-1',
    ]);

    importOpportunityRow([
        'type' => 'tender',
        'title' => 'Corrected title',
        'source' => 'CSV',
        'source_ref' => 'DUP-1',
    ]);

    expect(Opportunity::count())->toBe(1)
        ->and(Opportunity::sole()->title)->toBe('Corrected title');
});
