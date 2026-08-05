<?php

namespace App\Filament\Resources\BugReports;

use App\Models\BugReport;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Triage actions for the bug-report inbox, shared between the table rows and
 * the view page. Each stamps handled_by so it's clear who actioned it.
 */
class BugReportActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::triage(),
            self::resolve(),
            self::dismiss(),
        ];
    }

    public static function triage(): Action
    {
        return Action::make('triage')
            ->label('Mark triaged')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->visible(fn (BugReport $record) => $record->status === BugReport::STATUS_OPEN)
            ->action(function (BugReport $record): void {
                $record->update([
                    'status' => BugReport::STATUS_TRIAGED,
                    'handled_by' => auth()->id(),
                ]);

                Notification::make()->title('Bug report triaged')->success()->send();
            });
    }

    public static function resolve(): Action
    {
        return Action::make('resolve')
            ->label('Resolve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (BugReport $record) => $record->isOpen())
            ->schema([
                Textarea::make('note')->label('Resolution note')->maxLength(1000),
            ])
            ->action(function (BugReport $record, array $data): void {
                $record->update([
                    'status' => BugReport::STATUS_RESOLVED,
                    'resolution_note' => $data['note'] ?? null,
                    'handled_by' => auth()->id(),
                ]);

                Notification::make()->title('Bug report resolved')->success()->send();
            });
    }

    public static function dismiss(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (BugReport $record) => $record->isOpen())
            ->schema([
                Textarea::make('note')->label('Reason')->maxLength(1000),
            ])
            ->action(function (BugReport $record, array $data): void {
                $record->update([
                    'status' => BugReport::STATUS_DISMISSED,
                    'resolution_note' => $data['note'] ?? null,
                    'handled_by' => auth()->id(),
                ]);

                Notification::make()->title('Bug report dismissed')->success()->send();
            });
    }
}
