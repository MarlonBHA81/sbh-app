<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
 * Discount codes (Commerce). Platform-wide when no store is set, otherwise
 * scoped to a single store. Applied at checkout via a coupon code.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()->maxLength(60)->unique(ignoreRecord: true)
                ->helperText('Stored upper-cased; buyers enter it at checkout.'),
            Select::make('store_id')
                ->label('Store')
                ->relationship('store', 'name')
                ->searchable()->preload()
                ->helperText('Leave blank for a platform-wide coupon.'),
            Select::make('type')
                ->required()
                ->options([
                    Coupon::TYPE_PERCENT => 'Percentage off',
                    Coupon::TYPE_FIXED => 'Fixed amount off',
                ])
                ->live(),
            TextInput::make('value')
                ->required()->numeric()->minValue(0)
                ->label(fn ($get) => $get('type') === Coupon::TYPE_FIXED ? 'Amount off (cents)' : 'Percent off (0–100)')
                ->maxValue(fn ($get) => $get('type') === Coupon::TYPE_PERCENT ? 100 : null),
            TextInput::make('min_spend_cents')->numeric()->minValue(0)
                ->label('Minimum spend (cents)')->helperText('Optional.'),
            TextInput::make('max_redemptions')->numeric()->minValue(1)
                ->label('Max redemptions')->helperText('Optional. Blank = unlimited.'),
            DateTimePicker::make('starts_at')->helperText('Optional.'),
            DateTimePicker::make('ends_at')->helperText('Optional.'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->badge(),
                TextColumn::make('store.name')->label('Store')->placeholder('Platform-wide')->toggleable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('value')
                    ->formatStateUsing(fn (int $state, Coupon $r) => $r->type === Coupon::TYPE_PERCENT
                        ? $state.'%'
                        : number_format($state / 100, 2)),
                TextColumn::make('redeemed_count')->label('Used')
                    ->formatStateUsing(fn (int $state, Coupon $r) => $r->max_redemptions ? "{$state}/{$r->max_redemptions}" : (string) $state),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('ends_at')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
