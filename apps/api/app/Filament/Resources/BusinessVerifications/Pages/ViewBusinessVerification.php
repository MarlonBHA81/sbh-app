<?php

namespace App\Filament\Resources\BusinessVerifications\Pages;

use App\Filament\Resources\BusinessVerifications\BusinessVerificationResource;
use App\Filament\Resources\BusinessVerifications\VerificationActions;
use App\Models\BusinessVerificationDocument;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessVerification extends ViewRecord
{
    protected static string $resource = BusinessVerificationResource::class;

    protected function getHeaderActions(): array
    {
        // Review actions, plus one "download" action per submitted document that
        // opens the admin-gated streaming route in a new tab.
        $downloads = $this->record->documents
            ->map(fn (BusinessVerificationDocument $doc) => Action::make("download_{$doc->id}")
                ->label('Download: '.$doc->type)
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(url("/api/v1/admin/verifications/documents/{$doc->ulid}/download"))
                ->openUrlInNewTab())
            ->all();

        return [...VerificationActions::all(), ...$downloads];
    }
}
