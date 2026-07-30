<?php

namespace App\Filament\Resources\Opportunities\Pages;

use App\Filament\Imports\OpportunityImporter;
use App\Filament\Resources\Opportunities\OpportunityResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListOpportunities extends ListRecords
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(OpportunityImporter::class)
                ->label('Import CSV'),
            CreateAction::make(),
        ];
    }
}
