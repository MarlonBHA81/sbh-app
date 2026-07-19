<?php

namespace App\Filament\Resources\BriefItems\Pages;

use App\Filament\Resources\BriefItems\BriefItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBriefItem extends CreateRecord
{
    protected static string $resource = BriefItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
