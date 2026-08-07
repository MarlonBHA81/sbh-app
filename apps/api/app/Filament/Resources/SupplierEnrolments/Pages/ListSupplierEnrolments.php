<?php

namespace App\Filament\Resources\SupplierEnrolments\Pages;

use App\Filament\Resources\SupplierEnrolments\SupplierEnrolmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierEnrolments extends ListRecords
{
    protected static string $resource = SupplierEnrolmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Enrol supplier')];
    }
}
