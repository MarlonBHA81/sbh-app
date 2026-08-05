<?php

namespace App\Filament\Resources\BriefItems\Pages;

use App\Filament\Resources\BriefItems\BriefItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBriefItem extends EditRecord
{
    protected static string $resource = BriefItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
