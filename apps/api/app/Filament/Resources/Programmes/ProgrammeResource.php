<?php

namespace App\Filament\Resources\Programmes;

use App\Filament\Resources\Programmes\Pages\CreateProgramme;
use App\Filament\Resources\Programmes\Pages\EditProgramme;
use App\Filament\Resources\Programmes\Pages\ListProgrammes;
use App\Models\Profile;
use App\Models\Programme;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Enterprise & Supplier Development programmes (operator-run MVP). Each is owned
 * by a corporate profile; cohorts and supplier enrolments hang off it.
 */
class ProgrammeResource extends Resource
{
    protected static ?string $model = Programme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'ESD';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('profile_id')
                ->label('Corporate sponsor')
                ->relationship('corporate', 'name', fn ($query) => $query->corporate())
                ->searchable()->preload()->required()
                ->helperText('Only corporate profiles can sponsor a programme.'),
            TextInput::make('name')->required()->maxLength(160),
            Select::make('type')
                ->required()
                ->options([
                    Programme::TYPE_SUPPLIER_DEVELOPMENT => 'Supplier development',
                    Programme::TYPE_ENTERPRISE_DEVELOPMENT => 'Enterprise development',
                ])
                ->default(Programme::TYPE_SUPPLIER_DEVELOPMENT),
            Select::make('status')
                ->required()
                ->options([
                    Programme::STATUS_DRAFT => 'Draft',
                    Programme::STATUS_ACTIVE => 'Active',
                    Programme::STATUS_CLOSED => 'Closed',
                ])
                ->default(Programme::STATUS_DRAFT),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            DatePicker::make('starts_at'),
            DatePicker::make('ends_at'),
            TextInput::make('budget_cents')->numeric()->minValue(0)
                ->label('Budget (cents)')->helperText('Optional total ESD/SD budget.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('corporate')->withCount('cohorts'))
            ->columns([
                TextColumn::make('corporate.name')->label('Sponsor')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Programme::STATUS_ACTIVE => 'success',
                        Programme::STATUS_DRAFT => 'gray',
                        Programme::STATUS_CLOSED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('cohorts_count')->label('Cohorts')->badge(),
                TextColumn::make('starts_at')->date()->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Programme::STATUS_DRAFT => 'Draft',
                    Programme::STATUS_ACTIVE => 'Active',
                    Programme::STATUS_CLOSED => 'Closed',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgrammes::route('/'),
            'create' => CreateProgramme::route('/create'),
            'edit' => EditProgramme::route('/{record}/edit'),
        ];
    }
}
