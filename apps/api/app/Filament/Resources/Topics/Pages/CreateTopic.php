<?php

namespace App\Filament\Resources\Topics\Pages;

use App\Filament\Resources\Topics\TopicResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTopic extends CreateRecord
{
    protected static string $resource = TopicResource::class;

    protected function afterCreate(): void
    {
        // Ensure the materialized path reflects the chosen parent.
        $this->record->syncPath();
        $this->record->save();
    }
}
