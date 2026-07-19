<?php

namespace App\Filament\Resources\LessonTracks\Pages;

use App\Filament\Resources\LessonTracks\LessonTrackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLessonTrack extends EditRecord
{
    protected static string $resource = LessonTrackResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
