<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
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
 * Store products (Shop P1) — admin moderation of vendor products.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(160),
            Select::make('type')
                ->required()
                ->options(collect(Product::TYPES)->mapWithKeys(fn (string $t) => [$t => Str::headline($t)])->all()),
            Textarea::make('description')->required()->rows(4)->maxLength(5000),
            TextInput::make('price_cents')->numeric()->minValue(0)
                ->helperText('Price in cents (e.g. 9900 = R99.00).'),
            TextInput::make('currency')->maxLength(3)->default('ZAR'),
            TextInput::make('external_url')->url()->maxLength(500),
            Toggle::make('is_published')
                ->helperText('Off keeps it as a draft, hidden from the storefront.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(40)->sortable(),
                TextColumn::make('store.name')->label('Store')->searchable()->toggleable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('price_cents')->label('Price')
                    ->formatStateUsing(fn (?int $state, Product $r) => $state === null ? '—' : $r->currency.' '.number_format($state / 100, 2)),
                IconColumn::make('is_published')->boolean()->label('Published')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(Product::TYPES)
                    ->mapWithKeys(fn (string $t) => [$t => Str::headline($t)])->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
