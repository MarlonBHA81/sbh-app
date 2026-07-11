<?php

namespace App\Filament\Resources\Topics\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TopicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, callable $set) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug($state));
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('icon')->maxLength(255),
            Select::make('parent_id')
                ->label('Parent')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Textarea::make('description')->maxLength(1000)->columnSpanFull(),
        ]);
    }
}
