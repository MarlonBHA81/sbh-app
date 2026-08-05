<?php

namespace App\Filament\Resources\Reports;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Support\Moderation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * The moderation-queue actions, shared between the table rows and the report
 * view page. Every action logs to moderation_actions and stamps handled_by.
 */
class ReportActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [
            self::startReview(),
            self::resolve(),
            self::dismiss(),
            self::deleteContent(),
            self::banAuthor(),
        ];
    }

    public static function startReview(): Action
    {
        return Action::make('start_review')
            ->label('Start review')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->visible(fn (Report $record) => $record->status === Report::STATUS_PENDING)
            ->action(function (Report $record): void {
                $record->update([
                    'status' => Report::STATUS_REVIEWING,
                    'handled_by' => auth()->id(),
                ]);

                Moderation::log('report.review', $record);

                Notification::make()->title('Report under review')->success()->send();
            });
    }

    public static function resolve(): Action
    {
        return Action::make('resolve')
            ->label('Resolve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Report $record) => $record->isOpen())
            ->schema([
                Textarea::make('note')->label('Resolution note')->maxLength(1000),
            ])
            ->action(function (Report $record, array $data): void {
                $record->update([
                    'status' => Report::STATUS_RESOLVED,
                    'resolution_note' => $data['note'] ?? null,
                    'handled_by' => auth()->id(),
                ]);

                Moderation::log('report.resolve', $record, ['note' => $data['note'] ?? null]);

                Notification::make()->title('Report resolved')->success()->send();
            });
    }

    public static function dismiss(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (Report $record) => $record->isOpen())
            ->schema([
                Textarea::make('note')->label('Reason')->maxLength(1000),
            ])
            ->action(function (Report $record, array $data): void {
                $record->update([
                    'status' => Report::STATUS_DISMISSED,
                    'resolution_note' => $data['note'] ?? null,
                    'handled_by' => auth()->id(),
                ]);

                Moderation::log('report.dismiss', $record, ['note' => $data['note'] ?? null]);

                Notification::make()->title('Report dismissed')->success()->send();
            });
    }

    public static function deleteContent(): Action
    {
        return Action::make('delete_content')
            ->label('Delete content')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(function (Report $record) {
                $target = $record->reportable;

                return $target instanceof Post || $target instanceof Comment;
            })
            ->action(function (Report $record): void {
                $target = $record->reportable;

                if ($target instanceof Post || $target instanceof Comment) {
                    $target->delete();
                    Moderation::log('report.delete_content', $target, ['report_ulid' => $record->ulid]);
                }

                $record->update([
                    'status' => Report::STATUS_RESOLVED,
                    'handled_by' => auth()->id(),
                ]);

                Notification::make()->title('Content deleted')->success()->send();
            });
    }

    public static function banAuthor(): Action
    {
        return Action::make('ban_author')
            ->label('Ban author')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Report $record) => self::authorUser($record) !== null)
            ->schema([
                Textarea::make('reason')->label('Reason')->required()->maxLength(500),
            ])
            ->action(function (Report $record, array $data): void {
                $user = self::authorUser($record);

                if ($user === null) {
                    Notification::make()->title('No author to ban')->danger()->send();

                    return;
                }

                $user->forceFill([
                    'banned_at' => now(),
                    'ban_reason' => $data['reason'],
                ])->save();
                $user->tokens()->delete();

                $record->update([
                    'status' => Report::STATUS_RESOLVED,
                    'handled_by' => auth()->id(),
                ]);

                Moderation::log('report.ban_author', $user, [
                    'report_ulid' => $record->ulid,
                    'reason' => $data['reason'],
                ]);

                Notification::make()->title('Author banned')->success()->send();
            });
    }

    /**
     * The user account behind the reported subject, if any.
     */
    public static function authorUser(Report $report): ?User
    {
        $target = $report->reportable;

        $profile = match (true) {
            $target instanceof Post, $target instanceof Comment => $target->profile,
            $target instanceof Profile => $target,
            default => null,
        };

        return $profile?->user;
    }

    /**
     * A short human-readable preview of the reported subject.
     */
    public static function preview(?Model $target): string
    {
        return match (true) {
            $target instanceof Post => $target->body
                ?: '[post '.$target->type.'] '.json_encode($target->payload),
            $target instanceof Comment => $target->body ?? '[deleted comment]',
            $target instanceof Profile => '@'.$target->handle.' — '.$target->name,
            default => '[content unavailable]',
        };
    }
}
