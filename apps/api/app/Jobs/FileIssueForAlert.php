<?php

namespace App\Jobs;

use App\Contracts\IssueTracker;
use App\Observability\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Files a triage issue for one inbound observability alert via the configured
 * IssueTracker (GitHub, or the null logger). Queued off the webhook request so
 * the receiver can always answer 200 fast; retries with backoff on API errors.
 */
class FileIssueForAlert implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public Alert $alert) {}

    public function handle(IssueTracker $tracker): void
    {
        $tracker->report($this->alert);
    }
}
