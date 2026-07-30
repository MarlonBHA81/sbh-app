<?php

namespace App\Filament\Resources\BugReports\Pages;

use App\Filament\Resources\BugReports\BugReportActions;
use App\Filament\Resources\BugReports\BugReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBugReport extends ViewRecord
{
    protected static string $resource = BugReportResource::class;

    protected function getHeaderActions(): array
    {
        return BugReportActions::all();
    }
}
