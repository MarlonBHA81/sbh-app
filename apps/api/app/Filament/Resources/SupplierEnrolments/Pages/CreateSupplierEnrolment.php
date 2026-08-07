<?php

namespace App\Filament\Resources\SupplierEnrolments\Pages;

use App\Filament\Resources\SupplierEnrolments\SupplierEnrolmentResource;
use App\Models\SupplierEnrolment;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierEnrolment extends CreateRecord
{
    protected static string $resource = SupplierEnrolmentResource::class;

    /** Stamp the enrolling operator and set the enrolled timestamp once confirmed. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        $confirmed = [
            SupplierEnrolment::STATUS_ACCEPTED,
            SupplierEnrolment::STATUS_ACTIVE,
            SupplierEnrolment::STATUS_COMPLETED,
        ];

        if (in_array($data['status'] ?? null, $confirmed, true)) {
            $data['enrolled_at'] ??= now();
        }

        return $data;
    }
}
