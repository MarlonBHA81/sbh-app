<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Models\Campaign;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('ulid')->label('Campaign'),
            TextEntry::make('profile.handle')->label('Advertiser'),
            TextEntry::make('post.body')->label('Promoted post')->limit(200),
            TextEntry::make('status')->badge(),
            TextEntry::make('budget_cents')
                ->label('Budget')
                ->formatStateUsing(fn (int $state) => 'R'.number_format($state / 100, 2)),
            TextEntry::make('spent_cents')
                ->label('Spent')
                ->formatStateUsing(fn (int $state) => 'R'.number_format($state / 100, 2)),
            TextEntry::make('cpi_cents')
                ->label('CPI')
                ->formatStateUsing(fn (int $state) => 'R'.number_format($state / 100, 2)),
            TextEntry::make('impressions_count')->label('Impressions'),
            TextEntry::make('clicks_count')->label('Clicks'),
            TextEntry::make('ctr')
                ->label('CTR')
                ->state(fn (Campaign $record) => $record->ctrPct().'%'),
            TextEntry::make('starts_at')->dateTime(),
            TextEntry::make('ends_at')->dateTime(),
        ]);
    }
}
