<?php

namespace App\Filament\Resources\BugReports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BugReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bug report')
                ->columns(2)
                ->schema([
                    TextEntry::make('summary')->columnSpanFull(),
                    TextEntry::make('details')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('created_at')->dateTime()->label('Reported at'),
                    TextEntry::make('resolution_note')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('handler.email')->label('Handled by')->placeholder('—'),
                ]),

            Section::make('Context')
                ->columns(2)
                ->schema([
                    TextEntry::make('user.email')->label('Reporter'),
                    TextEntry::make('profile.handle')->label('Active profile')->placeholder('—'),
                    TextEntry::make('url')->label('URL')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('app_version')->label('App version')->placeholder('—'),
                    TextEntry::make('user_agent')->label('User agent')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }
}
