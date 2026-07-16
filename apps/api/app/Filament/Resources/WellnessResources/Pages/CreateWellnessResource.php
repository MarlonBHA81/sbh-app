<?php

namespace App\Filament\Resources\WellnessResources\Pages;

use App\Filament\Resources\WellnessResources\WellnessResourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWellnessResource extends CreateRecord
{
    protected static string $resource = WellnessResourceResource::class;
}
