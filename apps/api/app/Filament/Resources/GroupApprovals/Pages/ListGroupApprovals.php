<?php

namespace App\Filament\Resources\GroupApprovals\Pages;

use App\Filament\Resources\GroupApprovals\GroupApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListGroupApprovals extends ListRecords
{
    protected static string $resource = GroupApprovalResource::class;
}
