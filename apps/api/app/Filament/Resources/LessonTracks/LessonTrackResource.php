<?php

namespace App\Filament\Resources\LessonTracks;

use App\Filament\Resources\LessonTracks\Pages\CreateLessonTrack;
use App\Filament\Resources\LessonTracks\Pages\EditLessonTrack;
use App\Filament\Resources\LessonTracks\Pages\ListLessonTracks;
use App\Models\LessonTrack;
use BackedEnum;
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
 * Admin-curated lesson tracks (V2 · LEARN): simple ordered groupings of
 * lessons, e.g. "Business foundations".
 */
class LessonTrackResource extends Resource
{
    protected static ?string $model = LessonTrack::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Lesson tracks';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('description')->maxLength(255),
            TextInput::make('position')->numeric()->default(0)
                ->helperText('Order among tracks (lowest first).'),
            Toggle::make('is_published')
                ->default(false)
                ->helperText('Off keeps it as a draft, hidden from the app.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('lessons_count')->counts('lessons')->label('Lessons'),
                TextColumn::make('position')->sortable(),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
            ])
            ->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLessonTracks::route('/'),
            'create' => CreateLessonTrack::route('/create'),
            'edit' => EditLessonTrack::route('/{record}/edit'),
        ];
    }
}
