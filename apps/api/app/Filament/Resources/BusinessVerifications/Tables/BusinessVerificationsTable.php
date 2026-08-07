<?php

namespace App\Filament\Resources\BusinessVerifications\Tables;

use App\Filament\Resources\BusinessVerifications\VerificationActions;
use App\Models\BusinessVerification;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BusinessVerificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['profile', 'reviewer']))
            ->columns([
                TextColumn::make('profile.handle')->label('Business')->searchable()->sortable(),
                TextColumn::make('legal_name')->label('Legal name')->searchable()->limit(40),
                TextColumn::make('registration_number')->label('Reg. no.')->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        BusinessVerification::STATUS_PENDING => 'warning',
                        BusinessVerification::STATUS_REVIEWING => 'info',
                        BusinessVerification::STATUS_APPROVED => 'success',
                        BusinessVerification::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('reviewer.email')->label('Reviewed by')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->label('Submitted')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        BusinessVerification::STATUS_PENDING => 'Pending',
                        BusinessVerification::STATUS_REVIEWING => 'Reviewing',
                        BusinessVerification::STATUS_APPROVED => 'Approved',
                        BusinessVerification::STATUS_REJECTED => 'Rejected',
                    ])
                    ->default(BusinessVerification::STATUS_PENDING),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...VerificationActions::all(),
                ]),
            ]);
    }
}
