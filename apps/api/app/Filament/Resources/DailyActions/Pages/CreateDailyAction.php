<?php

namespace App\Filament\Resources\DailyActions\Pages;

use App\Filament\Resources\DailyActions\DailyActionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyAction extends CreateRecord
{
    protected static string $resource = DailyActionResource::class;
}
