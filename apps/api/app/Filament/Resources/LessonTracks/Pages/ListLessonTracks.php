<?php

namespace App\Filament\Resources\LessonTracks\Pages;

use App\Filament\Resources\LessonTracks\LessonTrackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLessonTracks extends ListRecords
{
    protected static string $resource = LessonTrackResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
