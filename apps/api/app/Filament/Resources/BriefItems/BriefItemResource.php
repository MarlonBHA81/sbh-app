<?php

namespace App\Filament\Resources\BriefItems;

use App\Filament\Resources\BriefItems\Pages\CreateBriefItem;
use App\Filament\Resources\BriefItems\Pages\EditBriefItem;
use App\Filament\Resources\BriefItems\Pages\ListBriefItems;
use App\Models\BriefItem;
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
 * Admin-curated briefing items (V2 · Feature 7) that feed the Daily Business
 * Brief card. Optionally target an industry; leave it blank to reach everyone.
 * The `is_published` toggle controls whether an item appears in the app.
 */
class BriefItemResource extends Resource
{
    protected static ?string $model = BriefItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Brief items';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Select::make('kind')
                ->required()
                ->default('tip')
                ->options(collect(BriefItem::KINDS)
                    ->mapWithKeys(fn (string $k) => [$k => Str::headline($k)])
                    ->all()),
            Textarea::make('body')->required()->rows(4)->maxLength(2000),
            TextInput::make('industry')->maxLength(255)
                ->helperText('Optional. Leave blank to show to everyone; e.g. "Retail".'),
            TextInput::make('url')->url()->maxLength(500)
                ->helperText('Optional link to read more.'),
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
                TextColumn::make('kind')->badge()->sortable(),
                TextColumn::make('industry')->toggleable()->placeholder('Everyone')->searchable(),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
                TextColumn::make('published_at')->dateTime()->sortable()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kind')->options(collect(BriefItem::KINDS)
                    ->mapWithKeys(fn (string $k) => [$k => Str::headline($k)])
                    ->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBriefItems::route('/'),
            'create' => CreateBriefItem::route('/create'),
            'edit' => EditBriefItem::route('/{record}/edit'),
        ];
    }
}
