<?php

namespace App\Filament\Resources\WebhookEndpoints;

use App\Filament\Resources\WebhookEndpoints\Pages\CreateWebhookEndpoint;
use App\Filament\Resources\WebhookEndpoints\Pages\EditWebhookEndpoint;
use App\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
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
 * Outbound CRM/marketing webhooks (super-admin). Contact + commerce activity is
 * POSTed to these endpoints so a CRM (Brevo or any) can sync.
 */
class WebhookEndpointResource extends Resource
{
    protected static ?string $model = WebhookEndpoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'CRM webhooks';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('url')->url()->required()->maxLength(500)
                ->helperText('Brevo contacts: https://api.brevo.com/v3/contacts'),
            Select::make('format')
                ->required()
                ->default(WebhookEndpoint::FORMAT_GENERIC)
                ->options([
                    WebhookEndpoint::FORMAT_GENERIC => 'Generic (signed JSON)',
                    WebhookEndpoint::FORMAT_BREVO => 'Brevo (contact upsert)',
                ]),
            TextInput::make('secret')->password()->revealable()->maxLength(255)
                ->helperText('Generic mode: used to HMAC-sign the body (X-SBH-Signature).'),
            TextInput::make('header_name')->maxLength(120)
                ->helperText('Optional auth header name (Brevo uses "api-key").'),
            TextInput::make('header_value')->password()->revealable()->maxLength(500)
                ->helperText('The header value / API key.'),
            CheckboxList::make('events')
                ->options([
                    WebhookDispatcher::CONTACT_CREATED => 'Contact created',
                    WebhookDispatcher::CONTACT_UPDATED => 'Contact updated',
                    WebhookDispatcher::PURCHASE_COMPLETED => 'Purchase completed',
                ])
                ->helperText('Leave all unticked to receive every event.'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('format')->badge(),
                TextColumn::make('url')->limit(40)->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEndpoints::route('/'),
            'create' => CreateWebhookEndpoint::route('/create'),
            'edit' => EditWebhookEndpoint::route('/{record}/edit'),
        ];
    }
}
