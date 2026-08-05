<?php

namespace App\Filament\Resources\XpActions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class XpActionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Stable identifier referenced by the code that awards this action.'),
            TextInput::make('label')->required()->maxLength(255),
            TextInput::make('points')
                ->required()
                ->integer()
                ->helperText('May be negative for penalties.'),
            TextInput::make('daily_cap')
                ->integer()
                ->minValue(0)
                ->nullable()
                ->helperText('Maximum awards per day. Leave blank for no cap.'),
            Toggle::make('enabled')->default(true),
        ]);
    }
}
