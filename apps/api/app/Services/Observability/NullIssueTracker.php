<?php

namespace App\Services\Observability;

use App\Contracts\IssueTracker;
use App\Observability\Alert;
use Illuminate\Support\Facades\Log;

/**
 * Default driver: records the alert in the application log and files nothing.
 * Used when no issue tracker is configured (OBSERVABILITY_ISSUE_DRIVER=null),
 * so the alert receiver stays useful (captured + logged) without a GitHub token.
 */
class NullIssueTracker implements IssueTracker
{
    public function report(Alert $alert): ?string
    {
        Log::warning('Observability alert received (no issue tracker configured)', [
            'fingerprint' => $alert->fingerprint,
            'title' => $alert->title,
            'level' => $alert->level,
            'environment' => $alert->environment,
            'url' => $alert->url,
        ]);

        return null;
    }
}
