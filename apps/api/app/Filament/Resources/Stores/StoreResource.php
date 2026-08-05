<?php

namespace App\Filament\Resources\Stores;

use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Filament\Resources\Stores\Pages\EditStore;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Models\Store;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Vendor storefronts (Shop P1) — admin moderation. Stores are created by vendors;
 * admins review and can deactivate.
 */
class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Owning business profile — one store per profile (enforced by a
            // unique column). Admins pick it when loading a store for a vendor.
            Select::make('profile_id')
                ->label('Owner (business profile)')
                ->relationship('profile', 'name', fn ($query) => $query->where('kind', 'business'))
                ->searchable()
                ->preload()
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('The business profile this storefront belongs to.'),
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(160)->unique(ignoreRecord: true),
            TextInput::make('tagline')->maxLength(160),
            Textarea::make('about')->rows(3)->maxLength(5000),
            ColorPicker::make('brand_color'),
            ColorPicker::make('accent_color'),
            Textarea::make('policies')->rows(3)->maxLength(5000),
            Toggle::make('is_vat_registered')
                ->label('VAT registered')
                ->live()
                ->helperText('When on, the inclusive VAT portion is recorded on each order.'),
            TextInput::make('vat_rate_bp')
                ->label('VAT rate (basis points)')
                ->numeric()->minValue(0)->maxValue(10000)->default(1500)
                ->visible(fn ($get) => (bool) $get('is_vat_registered'))
                ->helperText('1500 = 15%. South-African standard rate.'),
            TextInput::make('vat_number')
                ->label('VAT number')
                ->maxLength(30)
                ->visible(fn ($get) => (bool) $get('is_vat_registered')),
            Toggle::make('is_active')
                ->helperText('Off hides the whole storefront and its products from the app.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->toggleable(),
                TextColumn::make('profile.name')->label('Owner')->searchable()->toggleable(),
                TextColumn::make('products_count')->counts('products')->label('Products'),
                IconColumn::make('is_vat_registered')->boolean()->label('VAT')->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }
}
