<?php

namespace App\Filament\Resources\DailyActions\Pages;

use App\Filament\Resources\DailyActions\DailyActionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyAction extends EditRecord
{
    protected static string $resource = DailyActionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
