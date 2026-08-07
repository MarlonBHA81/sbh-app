<?php

namespace App\Filament\Resources\Programmes\Pages;

use App\Filament\Resources\Programmes\ProgrammeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProgramme extends CreateRecord
{
    protected static string $resource = ProgrammeResource::class;

    /** Stamp the creating operator for the audit trail. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
