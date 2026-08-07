<?php

namespace App\Filament\Resources\SupplierEnrolments;

use App\Filament\Resources\SupplierEnrolments\Pages\CreateSupplierEnrolment;
use App\Filament\Resources\SupplierEnrolments\Pages\EditSupplierEnrolment;
use App\Filament\Resources\SupplierEnrolments\Pages\ListSupplierEnrolments;
use App\Filament\Resources\SupplierEnrolments\RelationManagers\DisbursementsRelationManager;
use App\Filament\Resources\SupplierEnrolments\RelationManagers\MilestonesRelationManager;
use App\Models\SupplierEnrolment;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Operator-run supplier enrolment management: add a verified supplier to a
 * cohort (an invite) and drive it through the review state machine. Suppliers
 * can also self-apply via the member API; those land here as "applied".
 */
class SupplierEnrolmentResource extends Resource
{
    protected static ?string $model = SupplierEnrolment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'ESD';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'ulid';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cohort_id')
                ->label('Cohort')
                ->relationship('cohort', 'name')
                ->searchable()->preload()->required(),
            Select::make('profile_id')
                ->label('Supplier')
                ->relationship('supplier', 'name', fn ($query) => $query->business()->where('is_verified', true))
                ->searchable()->preload()->required()
                ->helperText('Only verified business profiles can be enrolled as suppliers.'),
            Select::make('status')
                ->required()
                ->options(self::statusOptions())
                ->default(SupplierEnrolment::STATUS_INVITED),
            Textarea::make('decision_note')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['cohort.programme', 'supplier']))
            ->columns([
                TextColumn::make('cohort.programme.name')->label('Programme')->searchable()->sortable(),
                TextColumn::make('cohort.name')->label('Cohort')->searchable()->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        SupplierEnrolment::STATUS_ACTIVE, SupplierEnrolment::STATUS_ACCEPTED => 'success',
                        SupplierEnrolment::STATUS_COMPLETED => 'info',
                        SupplierEnrolment::STATUS_REJECTED, SupplierEnrolment::STATUS_WITHDRAWN => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('enrolled_at')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ...EnrolmentActions::all(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            SupplierEnrolment::STATUS_INVITED => 'Invited',
            SupplierEnrolment::STATUS_APPLIED => 'Applied',
            SupplierEnrolment::STATUS_ACCEPTED => 'Accepted',
            SupplierEnrolment::STATUS_ACTIVE => 'Active',
            SupplierEnrolment::STATUS_COMPLETED => 'Completed',
            SupplierEnrolment::STATUS_WITHDRAWN => 'Withdrawn',
            SupplierEnrolment::STATUS_REJECTED => 'Rejected',
        ];
    }

    public static function getRelations(): array
    {
        return [
            MilestonesRelationManager::class,
            DisbursementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierEnrolments::route('/'),
            'create' => CreateSupplierEnrolment::route('/create'),
            'edit' => EditSupplierEnrolment::route('/{record}/edit'),
        ];
    }
}
