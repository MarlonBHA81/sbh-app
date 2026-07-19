<?php

namespace App\Filament\Resources\BriefItems\Pages;

use App\Filament\Resources\BriefItems\BriefItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBriefItems extends ListRecords
{
    protected static string $resource = BriefItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
