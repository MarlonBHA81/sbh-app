<?php

namespace App\Filament\Resources\LibraryResources;

use App\Filament\Resources\LibraryResources\Pages\CreateLibraryResource;
use App\Filament\Resources\LibraryResources\Pages\EditLibraryResource;
use App\Filament\Resources\LibraryResources\Pages\ListLibraryResources;
use App\Models\LibraryResource;
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
 * Admin-curated Resource Library (V2 · LEARN): templates, checklists, toolkits
 * and AI prompts. The `is_published` toggle controls whether a resource appears
 * in the member-facing /resources screen.
 */
class LibraryResourceResource extends Resource
{
    protected static ?string $model = LibraryResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Resources';

    protected static ?string $modelLabel = 'resource';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Select::make('type')
                ->required()
                ->options(collect(LibraryResource::TYPES)
                    ->mapWithKeys(fn (string $t) => [$t => Str::headline($t)])
                    ->all()),
            Select::make('category')
                ->required()
                ->options(collect(LibraryResource::CATEGORIES)
                    ->mapWithKeys(fn (string $c) => [$c => Str::headline($c)])
                    ->all()),
            Textarea::make('description')->required()->rows(4)->maxLength(2000),
            TextInput::make('url')->required()->url()->maxLength(500)
                ->helperText('External link or file the resource points to.'),
            TextInput::make('industry')->maxLength(255)
                ->helperText('Optional industry filter, e.g. "Retail".'),
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
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('category')->badge()->sortable(),
                TextColumn::make('industry')->toggleable()->placeholder('—'),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(LibraryResource::TYPES)
                    ->mapWithKeys(fn (string $t) => [$t => Str::headline($t)])
                    ->all()),
                SelectFilter::make('category')->options(collect(LibraryResource::CATEGORIES)
                    ->mapWithKeys(fn (string $c) => [$c => Str::headline($c)])
                    ->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLibraryResources::route('/'),
            'create' => CreateLibraryResource::route('/create'),
            'edit' => EditLibraryResource::route('/{record}/edit'),
        ];
    }
}
