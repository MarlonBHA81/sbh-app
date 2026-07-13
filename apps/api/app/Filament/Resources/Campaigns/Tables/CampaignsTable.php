<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Filament\Resources\Campaigns\CampaignAdminActions;
use App\Models\Campaign;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['profile', 'post']))
            ->columns([
                TextColumn::make('profile.handle')->label('Advertiser')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Campaign::STATUS_ACTIVE => 'success',
                        Campaign::STATUS_PAUSED => 'warning',
                        Campaign::STATUS_COMPLETED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('spend_progress')
                    ->label('Spend')
                    ->state(fn (Campaign $record) => $record->budget_cents === null
                        ? '—'
                        : sprintf(
                            'R%s / R%s (%d%%)',
                            number_format($record->spent_cents / 100, 2),
                            number_format($record->budget_cents / 100, 2),
                            $record->budget_cents > 0
                                ? (int) round($record->spent_cents / $record->budget_cents * 100)
                                : 0,
                        )),
                TextColumn::make('impressions_count')->label('Impr.')->sortable(),
                TextColumn::make('clicks_count')->label('Clicks')->sortable(),
                TextColumn::make('link_clicks_count')->label('Link clicks')->sortable(),
                TextColumn::make('ctr')
                    ->label('CTR')
                    ->state(fn (Campaign $record) => number_format($record->ctrPct(), 1).'%'),
                TextColumn::make('ends_at')->dateTime()->label('Ends')->sortable(),
                TextColumn::make('created_at')->dateTime()->label('Created')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    Campaign::STATUS_ACTIVE => 'Active',
                    Campaign::STATUS_PAUSED => 'Paused',
                    Campaign::STATUS_COMPLETED => 'Completed',
                ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...CampaignAdminActions::all(),
                ]),
            ]);
    }
}
