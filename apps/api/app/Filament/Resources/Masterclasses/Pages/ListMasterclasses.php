<?php

namespace App\Filament\Resources\Masterclasses\Pages;

use App\Filament\Resources\Masterclasses\MasterclassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterclasses extends ListRecords
{
    protected static string $resource = MasterclassResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
