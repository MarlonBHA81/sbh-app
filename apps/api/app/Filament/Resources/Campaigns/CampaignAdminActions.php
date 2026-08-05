<?php

namespace App\Filament\Resources\Campaigns;

use App\Models\Campaign;
use App\Services\Ads\CampaignService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Admin campaign controls (pause, resume, end early), shared between the table
 * rows and the view page. All routed through CampaignService so the API and
 * the admin panel share one set of state-transition rules.
 */
class CampaignAdminActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::pause(),
            self::resume(),
            self::end(),
        ];
    }

    public static function pause(): Action
    {
        return Action::make('pause')
            ->label('Pause')
            ->icon(Heroicon::OutlinedPause)
            ->color('warning')
            ->visible(fn (Campaign $record) => $record->status === Campaign::STATUS_ACTIVE)
            ->action(function (Campaign $record): void {
                app(CampaignService::class)->setStatus($record, Campaign::STATUS_PAUSED);

                Notification::make()->title('Campaign paused')->success()->send();
            });
    }

    public static function resume(): Action
    {
        return Action::make('resume')
            ->label('Resume')
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->visible(fn (Campaign $record) => $record->status === Campaign::STATUS_PAUSED)
            ->action(function (Campaign $record): void {
                app(CampaignService::class)->setStatus($record, Campaign::STATUS_ACTIVE);

                Notification::make()->title('Campaign resumed')->success()->send();
            });
    }

    public static function end(): Action
    {
        return Action::make('end')
            ->label('End')
            ->icon(Heroicon::OutlinedStop)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Campaign $record) => ! $record->isCompleted())
            ->action(function (Campaign $record): void {
                app(CampaignService::class)->end($record);

                Notification::make()->title('Campaign ended')->success()->send();
            });
    }
}
