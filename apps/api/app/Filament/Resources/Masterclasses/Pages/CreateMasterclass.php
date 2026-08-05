<?php

namespace App\Filament\Resources\Masterclasses\Pages;

use App\Filament\Resources\Masterclasses\MasterclassResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMasterclass extends CreateRecord
{
    protected static string $resource = MasterclassResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
