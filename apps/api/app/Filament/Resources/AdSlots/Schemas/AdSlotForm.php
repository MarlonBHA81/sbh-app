<?php

namespace App\Filament\Resources\AdSlots\Schemas;

use App\Models\AdSlot;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Stable identifier the client requests this slot by.'),
            Select::make('placement')
                ->required()
                ->options([
                    AdSlot::PLACEMENT_RIGHT_RAIL => 'Right rail',
                    AdSlot::PLACEMENT_FEED_INLINE => 'Feed inline',
                ]),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('sponsor_name')->required()->maxLength(255),
            TextInput::make('sponsor_url')->required()->url()->maxLength(255),
            TextInput::make('body')
                ->label('Tagline')
                ->maxLength(255),
            FileUpload::make('image_path')
                ->label('Image')
                ->image()
                ->disk('public')
                ->directory('ad-slots')
                ->imagePreviewHeight('120'),
            TextInput::make('weight')
                ->required()
                ->integer()
                ->minValue(1)
                ->maxValue(255)
                ->default(1)
                ->helperText('Relative selection weight among active slots in the same placement.'),
            Toggle::make('active')->default(true),
        ]);
    }
}
