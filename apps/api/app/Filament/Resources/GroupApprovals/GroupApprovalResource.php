<?php

namespace App\Filament\Resources\GroupApprovals;

use App\Filament\Resources\GroupApprovals\Pages\ListGroupApprovals;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Notifications\GroupApprovalDecided;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Moderation queue for member-created groups: anyone can create a group,
 * but it only becomes usable once an admin approves it here.
 */
class GroupApprovalResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Group approvals';

    protected static ?string $modelLabel = 'group';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('kind', Conversation::KIND_GROUP)
            ->with(['participants.profile']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('creator')
                    ->label('Created by')
                    ->state(fn (Conversation $record) => self::owner($record)?->handle ?? '—'),
                TextColumn::make('members')
                    ->state(fn (Conversation $record) => $record->participants
                        ->whereNull('left_at')
                        ->count()),
                TextColumn::make('approval_status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Conversation::APPROVAL_PENDING => 'warning',
                        Conversation::APPROVAL_APPROVED => 'success',
                        Conversation::APPROVAL_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('approval_status')
                    ->options([
                        Conversation::APPROVAL_PENDING => 'Pending',
                        Conversation::APPROVAL_APPROVED => 'Approved',
                        Conversation::APPROVAL_REJECTED => 'Rejected',
                    ])
                    ->default(Conversation::APPROVAL_PENDING),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Conversation $record) => $record->approval_status !== Conversation::APPROVAL_APPROVED)
                    ->requiresConfirmation()
                    ->action(fn (Conversation $record) => self::decide($record, true)),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (Conversation $record) => $record->approval_status === Conversation::APPROVAL_PENDING)
                    ->requiresConfirmation()
                    ->action(fn (Conversation $record) => self::decide($record, false)),
            ]);
    }

    public static function decide(Conversation $conversation, bool $approved): void
    {
        $conversation->update([
            'approval_status' => $approved
                ? Conversation::APPROVAL_APPROVED
                : Conversation::APPROVAL_REJECTED,
            'approved_at' => $approved ? now() : null,
        ]);

        $owner = self::owner($conversation);

        if ($owner?->profile?->user) {
            $owner->profile->user->notify(
                new GroupApprovalDecided($owner->profile, $conversation, $approved),
            );
        }
    }

    private static function owner(Conversation $conversation): ?ConversationParticipant
    {
        return $conversation->participants
            ->firstWhere('role', ConversationParticipant::ROLE_OWNER);
    }

    /** Pending groups badge in the admin nav. */
    public static function getNavigationBadge(): ?string
    {
        $count = Conversation::query()
            ->where('kind', Conversation::KIND_GROUP)
            ->where('approval_status', Conversation::APPROVAL_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroupApprovals::route('/'),
        ];
    }
}
