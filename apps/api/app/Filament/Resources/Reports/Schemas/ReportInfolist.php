<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Filament\Resources\Reports\ReportActions;
use App\Models\Report;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Report')
                ->columns(2)
                ->schema([
                    TextEntry::make('category')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('reporter.handle')->label('Reporter'),
                    TextEntry::make('created_at')->dateTime()->label('Reported at'),
                    TextEntry::make('details')->label('Reporter details')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('resolution_note')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('handler.email')->label('Handled by')->placeholder('—'),
                ]),

            Section::make('Reported content')
                ->schema([
                    TextEntry::make('reportable_type')
                        ->label('Kind')
                        ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                    TextEntry::make('preview')
                        ->label('Preview')
                        ->state(fn (Report $record) => ReportActions::preview($record->reportable))
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
