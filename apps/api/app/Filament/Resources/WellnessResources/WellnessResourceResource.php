<?php

namespace App\Filament\Resources\WellnessResources;

use App\Filament\Resources\WellnessResources\Pages\CreateWellnessResource;
use App\Filament\Resources\WellnessResources\Pages\EditWellnessResource;
use App\Filament\Resources\WellnessResources\Pages\ListWellnessResources;
use App\Models\WellnessResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Admin-curated wellness prompts/reads (V3 · BELONG). Supportive content only;
 * the `is_published` toggle controls whether it appears in the calm space.
 */
class WellnessResourceResource extends Resource
{
    protected static ?string $model = WellnessResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Wellness';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Select::make('category')
                ->required()
                ->default('reflection')
                ->options(collect(WellnessResource::CATEGORIES)
                    ->mapWithKeys(fn (string $c) => [$c => Str::headline($c)])
                    ->all()),
            Textarea::make('body')->required()->rows(6)->maxLength(5000)
                ->helperText('A short, supportive prompt or read. Keep it gentle and pressure-free.'),
            TextInput::make('position')->numeric()->default(0)
                ->helperText('Lower numbers show first.'),
            Toggle::make('is_published')
                ->default(false)
                ->helperText('Off keeps it as a draft, hidden from the app.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(50)->sortable(),
                TextColumn::make('category')->badge()->sortable(),
                TextColumn::make('position')->sortable(),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->options(collect(WellnessResource::CATEGORIES)
                    ->mapWithKeys(fn (string $c) => [$c => Str::headline($c)])
                    ->all()),
            ])
            ->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWellnessResources::route('/'),
            'create' => CreateWellnessResource::route('/create'),
            'edit' => EditWellnessResource::route('/{record}/edit'),
        ];
    }
}
