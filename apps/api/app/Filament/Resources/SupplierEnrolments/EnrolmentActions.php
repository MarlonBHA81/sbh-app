<?php

namespace App\Filament\Resources\SupplierEnrolments;

use App\Models\SupplierEnrolment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Review actions for a supplier enrolment, shared between the table rows and the
 * edit page. Each delegates to the model's state-machine methods, which stamp
 * the actor and record the transition in the activity log.
 */
class EnrolmentActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::accept(),
            self::activate(),
            self::complete(),
            self::reject(),
        ];
    }

    public static function accept(): Action
    {
        return Action::make('accept')
            ->label('Accept')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (SupplierEnrolment $record) => $record->isPending())
            ->schema([
                Textarea::make('note')->label('Note (optional)')->maxLength(1000),
            ])
            ->action(function (SupplierEnrolment $record, array $data): void {
                $record->accept(self::actor(), $data['note'] ?? null);

                Notification::make()->title('Enrolment accepted')->success()->send();
            });
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Mark active')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->visible(fn (SupplierEnrolment $record) => $record->status === SupplierEnrolment::STATUS_ACCEPTED)
            ->action(function (SupplierEnrolment $record): void {
                $record->activate(self::actor());

                Notification::make()->title('Supplier is now active')->success()->send();
            });
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label('Mark completed')
            ->icon(Heroicon::OutlinedFlag)
            ->color('info')
            ->visible(fn (SupplierEnrolment $record) => $record->status === SupplierEnrolment::STATUS_ACTIVE)
            ->action(function (SupplierEnrolment $record): void {
                $record->complete(self::actor());

                Notification::make()->title('Enrolment completed')->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (SupplierEnrolment $record) => $record->isPending())
            ->schema([
                Textarea::make('reason')->label('Reason (optional)')->maxLength(1000),
            ])
            ->action(function (SupplierEnrolment $record, array $data): void {
                $record->reject(self::actor(), $data['reason'] ?? null);

                Notification::make()->title('Enrolment rejected')->success()->send();
            });
    }

    private static function actor(): User
    {
        return auth()->user();
    }
}
