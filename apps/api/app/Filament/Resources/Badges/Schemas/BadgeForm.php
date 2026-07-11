<?php

namespace App\Filament\Resources\Badges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BadgeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('icon')->maxLength(255),
            Select::make('kind')->required()->options([
                'category' => 'Category',
                'verification' => 'Verification',
                'rank' => 'Rank',
            ]),
            Textarea::make('description')->maxLength(1000)->columnSpanFull(),
        ]);
    }
}
