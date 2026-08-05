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

            Section::make('AI assessment')
                ->columns(2)
                ->visible(fn (Report $record) => ! empty($record->ai_assessment))
                ->schema([
                    TextEntry::make('ai_assessment.flagged')
                        ->label('Flagged')
                        ->badge()
                        ->state(fn (Report $record) => ($record->ai_assessment['flagged'] ?? false) ? 'Flagged' : 'Clear')
                        ->color(fn (Report $record) => ($record->ai_assessment['flagged'] ?? false) ? 'danger' : 'success'),
                    TextEntry::make('ai_assessment.confidence')
                        ->label('Confidence')
                        ->state(fn (Report $record) => number_format((float) ($record->ai_assessment['confidence'] ?? 0) * 100).'%'),
                    TextEntry::make('ai_assessment.categories')
                        ->label('Categories')
                        ->badge()
                        ->state(fn (Report $record) => $record->ai_assessment['categories'] ?? [])
                        ->placeholder('—'),
                    TextEntry::make('ai_assessment.summary')
                        ->label('Summary')
                        ->state(fn (Report $record) => $record->ai_assessment['summary'] ?? null)
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
