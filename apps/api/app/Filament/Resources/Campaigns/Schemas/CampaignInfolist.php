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
                ->placeholder('No budget (metrics only)')
                ->formatStateUsing(fn (?int $state) => $state === null ? null : 'R'.number_format($state / 100, 2)),
            TextEntry::make('spent_cents')
                ->label('Spent')
                ->visible(fn (Campaign $record) => $record->budget_cents !== null)
                ->formatStateUsing(fn (int $state) => 'R'.number_format($state / 100, 2)),
            TextEntry::make('impressions_count')->label('Impressions'),
            TextEntry::make('clicks_count')->label('Post opens'),
            TextEntry::make('link_clicks_count')->label('Link clicks'),
            TextEntry::make('ctr')
                ->label('CTR')
                ->state(fn (Campaign $record) => $record->ctrPct().'%'),
            TextEntry::make('link_ctr')
                ->label('Link CTR')
                ->state(fn (Campaign $record) => $record->linkCtrPct().'%'),
            TextEntry::make('starts_at')->dateTime(),
            TextEntry::make('ends_at')->dateTime(),
        ]);
    }
}
