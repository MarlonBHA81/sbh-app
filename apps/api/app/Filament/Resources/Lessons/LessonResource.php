<?php

namespace App\Filament\Resources\Lessons;

use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Models\Lesson;
use App\Models\LessonTrack;
use App\Models\Profile;
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
 * Admin-curated bite-size lessons (V2 · LEARN). Each lesson has an inline body
 * or an external link, a ~5-minute estimate, an optional track and journey
 * stage. The `is_published` toggle controls whether it appears in /learn.
 */
class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Select::make('lesson_track_id')
                ->label('Track')
                ->options(fn () => LessonTrack::query()->orderBy('position')->pluck('title', 'id'))
                ->searchable()
                ->placeholder('No track (standalone)'),
            Textarea::make('body')->rows(8)->maxLength(10000)
                ->helperText('The lesson text. Leave blank if you use an external link instead.'),
            TextInput::make('external_url')->url()->maxLength(500)
                ->helperText('Optional external link if the lesson lives elsewhere.'),
            TextInput::make('minutes')->numeric()->default(5)->minValue(1)->maxValue(60)
                ->helperText('Estimated reading time in minutes.'),
            Select::make('journey_stage')
                ->options(collect(Profile::JOURNEY_STAGES)
                    ->mapWithKeys(fn (string $s) => [$s => Str::headline($s)])
                    ->all())
                ->placeholder('Any stage'),
            TextInput::make('position')->numeric()->default(0)
                ->helperText('Order within the track (lowest first).'),
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
                TextColumn::make('track.title')->label('Track')->placeholder('—')->toggleable(),
                TextColumn::make('journey_stage')->badge()->placeholder('—')->toggleable(),
                TextColumn::make('minutes')->suffix(' min')->sortable(),
                TextColumn::make('position')->sortable(),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
            ])
            ->filters([
                SelectFilter::make('lesson_track_id')
                    ->label('Track')
                    ->options(fn () => LessonTrack::query()->orderBy('position')->pluck('title', 'id')),
            ])
            ->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLessons::route('/'),
            'create' => CreateLesson::route('/create'),
            'edit' => EditLesson::route('/{record}/edit'),
        ];
    }
}
