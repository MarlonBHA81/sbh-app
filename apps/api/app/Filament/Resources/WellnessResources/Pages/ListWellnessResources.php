<?php

namespace App\Filament\Resources\WellnessResources\Pages;

use App\Filament\Resources\WellnessResources\WellnessResourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWellnessResources extends ListRecords
{
    protected static string $resource = WellnessResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
