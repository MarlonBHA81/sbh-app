<?php

namespace App\Filament\Resources\DailyActions;

use App\Filament\Resources\DailyActions\Pages\CreateDailyAction;
use App\Filament\Resources\DailyActions\Pages\EditDailyAction;
use App\Filament\Resources\DailyActions\Pages\ListDailyActions;
use App\Models\DailyAction;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin-curated daily challenges (V1 · PROGRESS): small <15-minute actions
 * rotated one-per-day for every member. Completion awards XP and advances
 * the member's streak.
 */
class DailyActionResource extends Resource
{
    protected static ?string $model = DailyAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Daily challenges';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(120)
                ->helperText('The action, e.g. "Ask one customer for a testimonial".'),
            Textarea::make('description')
                ->rows(3)
                ->maxLength(1000)
                ->helperText('Optional extra guidance shown under the title.'),
            Toggle::make('is_active')
                ->default(true)
                ->helperText('Only active actions are rotated into the daily slot.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(60),
                IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
                TextColumn::make('created_at')->date()->sortable()->toggleable(),
            ])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyActions::route('/'),
            'create' => CreateDailyAction::route('/create'),
            'edit' => EditDailyAction::route('/{record}/edit'),
        ];
    }
}
