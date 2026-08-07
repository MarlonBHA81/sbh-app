<?php

namespace App\Filament\Resources\Cohorts\Pages;

use App\Filament\Resources\Cohorts\CohortResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCohorts extends ListRecords
{
    protected static string $resource = CohortResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
