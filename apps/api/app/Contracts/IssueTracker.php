<?php

namespace App\Contracts;

use App\Observability\Alert;

/**
 * Files a triage issue for an inbound alert, de-duplicating on the alert's
 * fingerprint so a recurring error updates one issue instead of spamming new
 * ones. Implementations: NullIssueTracker (logs only), GithubIssueTracker.
 */
interface IssueTracker
{
    /**
     * Report an alert. Returns a reference to the issue (e.g. its URL) when one
     * was opened or updated, or null when nothing was filed.
     */
    public function report(Alert $alert): ?string;
}
