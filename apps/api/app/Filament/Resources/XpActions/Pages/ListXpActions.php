<?php

namespace App\Filament\Resources\XpActions\Pages;

use App\Filament\Resources\XpActions\XpActionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListXpActions extends ListRecords
{
    protected static string $resource = XpActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
