<?php

namespace App\Filament\Resources\XpActions\Pages;

use App\Filament\Resources\XpActions\XpActionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditXpAction extends EditRecord
{
    protected static string $resource = XpActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
