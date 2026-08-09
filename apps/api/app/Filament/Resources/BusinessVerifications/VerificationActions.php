<?php

namespace App\Filament\Resources\BusinessVerifications;

use App\Contracts\CipcVerifier;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\Business\CipcResult;
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
            self::checkCipc(),
            self::approve(),
            self::reject(),
        ];
    }

    /**
     * Run (or re-run) the automated CIPC registration lookup. On a confirmed
     * hit the profile gets its "CIPC verified" sticker (and XP); the result is
     * recorded on the submission either way.
     */
    public static function checkCipc(): Action
    {
        return Action::make('check_cipc')
            ->label('Check CIPC')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->color('info')
            ->visible(fn (BusinessVerification $record) => filled($record->registration_number))
            ->action(function (BusinessVerification $record): void {
                $result = $record->runCipcCheck(app(CipcVerifier::class), self::reviewer());

                match ($result->status) {
                    CipcResult::VERIFIED => Notification::make()
                        ->title('CIPC verified')
                        ->body($result->registeredName ?? '')
                        ->success()->send(),
                    CipcResult::NOT_FOUND => Notification::make()
                        ->title('Not found on CIPC')->warning()->send(),
                    default => Notification::make()
                        ->title('CIPC lookup unavailable')
                        ->body('Check the CIPC integration settings.')->danger()->send(),
                };
            });
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
