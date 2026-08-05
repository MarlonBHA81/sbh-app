<?php

namespace App\Filament\Resources\Masterclasses\Pages;

use App\Filament\Resources\Masterclasses\MasterclassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterclass extends EditRecord
{
    protected static string $resource = MasterclassResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
