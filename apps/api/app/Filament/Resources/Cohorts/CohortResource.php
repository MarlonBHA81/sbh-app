<?php

namespace App\Filament\Resources\Cohorts;

use App\Filament\Resources\Cohorts\Pages\CreateCohort;
use App\Filament\Resources\Cohorts\Pages\EditCohort;
use App\Filament\Resources\Cohorts\Pages\ListCohorts;
use App\Models\Cohort;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Cohorts (intakes) within an ESD programme. Supplier enrolments (ESD-2) attach
 * to a cohort.
 */
class CohortResource extends Resource
{
    protected static ?string $model = Cohort::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'ESD';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('programme_id')
                ->label('Programme')
                ->relationship('programme', 'name')
                ->searchable()->preload()->required(),
            TextInput::make('name')->required()->maxLength(160),
            Select::make('status')
                ->required()
                ->options([
                    Cohort::STATUS_ACTIVE => 'Active',
                    Cohort::STATUS_CLOSED => 'Closed',
                ])
                ->default(Cohort::STATUS_ACTIVE),
            DatePicker::make('starts_at'),
            DatePicker::make('ends_at'),
            TextInput::make('capacity')->numeric()->minValue(1)
                ->helperText('Optional. Blank = unlimited.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('programme'))
            ->columns([
                TextColumn::make('programme.name')->label('Programme')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === Cohort::STATUS_ACTIVE ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('capacity')->placeholder('Unlimited'),
                TextColumn::make('starts_at')->date()->placeholder('—')->toggleable(),
                TextColumn::make('ends_at')->date()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Cohort::STATUS_ACTIVE => 'Active',
                    Cohort::STATUS_CLOSED => 'Closed',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCohorts::route('/'),
            'create' => CreateCohort::route('/create'),
            'edit' => EditCohort::route('/{record}/edit'),
        ];
    }
}
