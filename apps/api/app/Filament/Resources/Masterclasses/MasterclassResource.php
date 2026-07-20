<?php

namespace App\Filament\Resources\Masterclasses;

use App\Filament\Resources\Masterclasses\Pages\CreateMasterclass;
use App\Filament\Resources\Masterclasses\Pages\EditMasterclass;
use App\Filament\Resources\Masterclasses\Pages\ListMasterclasses;
use App\Models\Masterclass;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
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
 * Admin-curated cohort programmes (V3 · LEARN). The `is_published` toggle
 * controls whether a masterclass appears in the app.
 */
class MasterclassResource extends Resource
{
    protected static ?string $model = Masterclass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('description')->required()->rows(4)->maxLength(5000),
            TextInput::make('facilitator_name')->maxLength(255)
                ->helperText('Who runs the programme (shown to members).'),
            DateTimePicker::make('starts_at')->required(),
            DateTimePicker::make('ends_at')->required(),
            TextInput::make('capacity')->numeric()->minValue(1)
                ->helperText('Leave blank for unlimited seats.'),
            Toggle::make('is_published')
                ->default(false)
                ->helperText('Off keeps it as a draft, hidden from the app.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(40)->sortable(),
                TextColumn::make('facilitator_name')->toggleable()->placeholder('—'),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('participants_count')->counts('participants')->label('Enrolled'),
                TextColumn::make('capacity')->placeholder('∞'),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMasterclasses::route('/'),
            'create' => CreateMasterclass::route('/create'),
            'edit' => EditMasterclass::route('/{record}/edit'),
        ];
    }
}
