<?php

namespace App\Filament\Resources\SupplierEnrolments\Pages;

use App\Filament\Resources\SupplierEnrolments\EnrolmentActions;
use App\Filament\Resources\SupplierEnrolments\SupplierEnrolmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierEnrolment extends EditRecord
{
    protected static string $resource = SupplierEnrolmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...EnrolmentActions::all(),
            DeleteAction::make(),
        ];
    }
}
