<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Manage a product's offer links: cross-sells ("you may also like"), order
 * bumps (offered at checkout) and post-purchase upsells. Admins/vendors wire
 * these up here — previously they only existed via seeders.
 */
class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';

    protected static ?string $title = 'Offers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('related_product_id')
                ->label('Related product')
                ->relationship('related', 'title')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('kind')
                ->required()
                ->options([
                    Product::OFFER_CROSS_SELL => 'Cross-sell',
                    Product::OFFER_BUMP => 'Order bump',
                    Product::OFFER_UPSELL => 'Upsell',
                ]),
            TextInput::make('discount_cents')
                ->label('Discount (cents)')
                ->numeric()->minValue(0)->default(0)
                ->helperText('Reduces the related product’s price when offered as a bump or upsell.'),
            TextInput::make('position')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kind')
            ->columns([
                TextColumn::make('related.title')->label('Product')->searchable(),
                TextColumn::make('kind')->badge()
                    ->formatStateUsing(fn (string $state) => Str::headline($state)),
                TextColumn::make('discount_cents')->label('Discount')
                    ->formatStateUsing(fn (?int $state) => $state ? number_format($state / 100, 2) : '—'),
                TextColumn::make('position')->sortable(),
            ])
            ->defaultSort('position')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
