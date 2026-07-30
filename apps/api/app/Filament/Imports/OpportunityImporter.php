<?php

namespace App\Filament\Imports;

use App\Models\Opportunity;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Bulk CSV import for opportunities/tenders/RFQs. Rows are upserted on
 * (source, source_ref) — the same dedupe contract the tender feed uses — so
 * re-importing a corrected file updates rather than duplicates. An unknown or
 * blank type falls back to "programme", mirroring the official-feed importer.
 */
class OpportunityImporter extends Importer
{
    protected static ?string $model = Opportunity::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('type')
                ->castStateUsing(function (?string $state): string {
                    $type = $state !== null ? strtolower(trim($state)) : '';

                    return in_array($type, Opportunity::TYPES, true) ? $type : 'programme';
                }),
            ImportColumn::make('title')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            // description is NOT NULL — coerce a blank cell to an empty string.
            ImportColumn::make('description')
                ->castStateUsing(fn (?string $state): string => $state ?? ''),
            ImportColumn::make('organisation'),
            ImportColumn::make('url')->rules(['nullable', 'url']),
            ImportColumn::make('source'),
            ImportColumn::make('source_url')->rules(['nullable', 'url']),
            ImportColumn::make('source_ref'),
            ImportColumn::make('is_official')->boolean(),
            ImportColumn::make('is_sponsored')->boolean(),
            ImportColumn::make('sponsor_name'),
            ImportColumn::make('sponsor_url')->rules(['nullable', 'url']),
            ImportColumn::make('industry'),
            ImportColumn::make('province'),
            ImportColumn::make('amount'),
            ImportColumn::make('closes_at')->rules(['nullable', 'date']),
            ImportColumn::make('is_published')->boolean(),
        ];
    }

    public function resolveRecord(): Opportunity
    {
        $source = filled($this->data['source'] ?? null) ? $this->data['source'] : 'CSV import';
        $ref = $this->data['source_ref'] ?? null;

        // Upsert on (source, source_ref) when a ref is present; otherwise always
        // create a new row (nothing to match on).
        $record = filled($ref)
            ? Opportunity::firstOrNew(['source' => $source, 'source_ref' => $ref])
            : new Opportunity(['source' => $source]);

        // description is NOT NULL; guarantee a value for rows that omit it.
        if ($record->description === null) {
            $record->description = '';
        }

        return $record;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows).' opportunit'
            .($import->successful_rows === 1 ? 'y' : 'ies').' imported.';

        if (($failed = $import->getFailedRowsCount()) > 0) {
            $body .= ' '.number_format($failed).' row'.($failed === 1 ? '' : 's').' failed.';
        }

        return $body;
    }
}
