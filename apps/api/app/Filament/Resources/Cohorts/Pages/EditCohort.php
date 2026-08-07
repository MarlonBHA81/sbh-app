<?php

namespace App\Filament\Resources\Cohorts\Pages;

use App\Filament\Resources\Cohorts\CohortResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCohort extends EditRecord
{
    protected static string $resource = CohortResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
