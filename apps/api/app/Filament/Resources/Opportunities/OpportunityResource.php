<?php

namespace App\Filament\Resources\Opportunities;

use App\Filament\Resources\Opportunities\Pages\CreateOpportunity;
use App\Filament\Resources\Opportunities\Pages\EditOpportunity;
use App\Filament\Resources\Opportunities\Pages\ListOpportunities;
use App\Models\Opportunity;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Admin-curated opportunities (V1 · GROW): tenders, funding, grants and
 * procurement brought to members. The `is_published` toggle controls whether
 * an opportunity appears in the app.
 */
class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Select::make('type')
                ->required()
                ->options(collect(Opportunity::TYPES)
                    ->mapWithKeys(fn (string $t) => [$t => Str::headline($t)])
                    ->all()),
            Textarea::make('description')->required()->rows(5)->maxLength(5000),
            TextInput::make('organisation')->maxLength(255)
                ->helperText('Who is offering it (e.g. SEDA, a bank, a corporate).'),
            TextInput::make('url')->url()->maxLength(500)
                ->helperText('Where members apply or read more.'),
            // Provenance (V3 · GROW): record where this came from and mark it
            // official when it's from a trusted/verified source.
            TextInput::make('source')->maxLength(255)
                ->helperText('Where it came from, e.g. "eTenders", "SEDA", "Manual".'),
            TextInput::make('source_url')->url()->maxLength(500)
                ->helperText('Canonical link to the original listing (if different from the apply URL).'),
            TextInput::make('source_ref')->maxLength(255)
                ->helperText('Optional external reference/ID — used to avoid duplicates when importing.'),
            Toggle::make('is_official')
                ->default(false)
                ->helperText('On shows an "Official" badge. Only enable for verified, trusted sources.'),
            TextInput::make('industry')->maxLength(255)
                ->helperText('Optional industry filter, e.g. "Retail".'),
            TextInput::make('province')->maxLength(255)
                ->helperText('Optional region filter, e.g. "Gauteng".'),
            TextInput::make('amount')->maxLength(255)
                ->helperText('Free text, e.g. "Up to R50,000".'),
            DatePicker::make('closes_at')
                ->helperText('Leave blank if it never closes; past-dated items are auto-hidden.'),
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
                TextColumn::make('organisation')->toggleable()->searchable(),
                TextColumn::make('source')->toggleable()->placeholder('—')->searchable(),
                IconColumn::make('is_official')->boolean()->label('Official')->sortable(),
                TextColumn::make('closes_at')->date()->sortable()->placeholder('—'),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(Opportunity::TYPES)
                    ->mapWithKeys(fn (string $t) => [$t => Str::headline($t)])
                    ->all()),
                TernaryFilter::make('is_official')->label('Official'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpportunities::route('/'),
            'create' => CreateOpportunity::route('/create'),
            'edit' => EditOpportunity::route('/{record}/edit'),
        ];
    }
}
