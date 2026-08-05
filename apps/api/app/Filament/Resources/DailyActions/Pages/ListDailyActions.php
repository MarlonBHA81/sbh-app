<?php

namespace App\Filament\Resources\DailyActions\Pages;

use App\Filament\Resources\DailyActions\DailyActionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyActions extends ListRecords
{
    protected static string $resource = DailyActionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
