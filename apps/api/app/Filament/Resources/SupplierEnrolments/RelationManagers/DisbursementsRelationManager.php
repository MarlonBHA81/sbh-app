<?php

namespace App\Filament\Resources\SupplierEnrolments\RelationManagers;

use App\Models\Disbursement;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * ED/SD spend for a supplier enrolment, managed inline on the enrolment edit
 * page. A line with no disbursed date is "planned"; setting it makes it actual.
 */
class DisbursementsRelationManager extends RelationManager
{
    protected static string $relationship = 'disbursements';

    protected static ?string $title = 'Disbursements';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount_cents')
                ->label('Amount (cents)')
                ->numeric()->minValue(0)->required(),
            TextInput::make('currency')->default('ZAR')->maxLength(3)->required(),
            Select::make('kind')
                ->required()
                ->options([
                    Disbursement::KIND_GRANT => 'Grant',
                    Disbursement::KIND_LOAN => 'Loan',
                    Disbursement::KIND_IN_KIND => 'In-kind',
                ])
                ->default(Disbursement::KIND_GRANT),
            DateTimePicker::make('disbursed_at')->seconds(false)
                ->helperText('Leave blank while planned; set once actually paid.'),
            TextInput::make('reference')->maxLength(120),
            Textarea::make('note')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, Disbursement $record) => $record->currency.' '.number_format($state / 100, 2)),
                TextColumn::make('kind')->badge(),
                TextColumn::make('disbursed_at')->dateTime()->placeholder('Planned')->sortable(),
                TextColumn::make('reference')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Disbursement $record) => ! $record->isPaid())
                    ->action(function (Disbursement $record): void {
                        $record->markDisbursed(auth()->user());

                        Notification::make()->title('Disbursement marked paid')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
