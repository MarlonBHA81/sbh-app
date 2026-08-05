<?php

namespace App\Filament\Resources\Ranks\Schemas;

use App\Models\Badge;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('icon')->maxLength(255)->helperText('Emoji shown next to the rank.'),
            TextInput::make('min_xp')->required()->integer()->minValue(0),
            TextInput::make('position')->required()->integer()->minValue(0),
            Select::make('badge_id')
                ->label('Rank badge')
                ->options(fn () => Badge::query()->where('kind', 'rank')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->helperText('The badge attached to profiles when they reach this rank.'),
        ]);
    }
}
