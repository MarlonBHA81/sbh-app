<?php

namespace App\Filament\Resources\BusinessVerifications;

use App\Models\BusinessVerification;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Review-queue actions for a business verification, shared between the table
 * rows and the view page. Each delegates to the model's state-machine methods,
 * which stamp the reviewer, log to moderation_actions and (on approve) flip the
 * profile's is_verified flag.
 */
class VerificationActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::startReview(),
            self::approve(),
            self::reject(),
        ];
    }

    public static function startReview(): Action
    {
        return Action::make('start_review')
            ->label('Start review')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->visible(fn (BusinessVerification $record) => $record->status === BusinessVerification::STATUS_PENDING)
            ->action(function (BusinessVerification $record): void {
                $record->markReviewing(self::reviewer());

                Notification::make()->title('Verification under review')->success()->send();
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve & verify')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (BusinessVerification $record) => $record->isOpen())
            ->schema([
                Textarea::make('note')->label('Note (optional)')->maxLength(1000),
            ])
            ->action(function (BusinessVerification $record, array $data): void {
                $record->approve(self::reviewer(), $data['note'] ?? null);

                Notification::make()->title('Business verified')->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (BusinessVerification $record) => $record->isOpen())
            ->schema([
                Textarea::make('reason')->label('Reason (shown to the member)')->required()->maxLength(1000),
            ])
            ->action(function (BusinessVerification $record, array $data): void {
                $record->reject(self::reviewer(), $data['reason']);

                Notification::make()->title('Verification rejected')->success()->send();
            });
    }

    private static function reviewer(): User
    {
        return auth()->user();
    }
}
