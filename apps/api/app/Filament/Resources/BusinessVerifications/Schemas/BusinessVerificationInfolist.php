<?php

namespace App\Filament\Resources\BusinessVerifications\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BusinessVerificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submission')
                ->columns(2)
                ->schema([
                    TextEntry::make('profile.handle')->label('Business'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('legal_name')->label('Legal name'),
                    TextEntry::make('registration_number')->label('Registration number')->placeholder('—'),
                    TextEntry::make('submitter.email')->label('Submitted by')->placeholder('—'),
                    TextEntry::make('created_at')->dateTime()->label('Submitted at'),
                    TextEntry::make('reviewer.email')->label('Reviewed by')->placeholder('—'),
                    TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
                    TextEntry::make('decision_note')->label('Decision note')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Documents')
                ->description('Open each document to review it (streamed from private storage). Use the header buttons to download.')
                ->schema([
                    RepeatableEntry::make('documents')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('type')->badge(),
                            TextEntry::make('original_name')->label('File')->placeholder('—'),
                            TextEntry::make('mime')->label('Type')->placeholder('—'),
                        ])
                        ->columns(3),
                ]),
        ]);
    }
}
