<?php

namespace App\Filament\Resources\WellnessResources\Pages;

use App\Filament\Resources\WellnessResources\WellnessResourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWellnessResource extends EditRecord
{
    protected static string $resource = WellnessResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
