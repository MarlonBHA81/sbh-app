<?php

namespace App\Console\Commands;

use App\Services\System\HealthReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled deep health check. Logs a warning for any failed dependency and
 * when the queue backlog / failed-job count crosses its threshold, so the
 * observability pipeline (or a plain log alert) can escalate. Exits non-zero
 * when a critical dependency is down.
 */
class HealthCheck extends Command
{
    protected $signature = 'system:health
        {--fail-threshold= : Override the failed-jobs warning threshold}
        {--backlog-threshold= : Override the queue-backlog warning threshold}';

    protected $description = 'Probe core dependencies (db/cache/storage/queue) and warn on trouble';

    public function handle(HealthReport $report): int
    {
        $result = $report->run();
        $checks = $result['checks'];

        foreach ($checks as $name => $check) {
            if (($check['ok'] ?? true) === false) {
                Log::warning("Health check failed: {$name}", $check);
            }
        }

        $failThreshold = (int) ($this->option('fail-threshold') ?? config('health.failed_jobs_threshold'));
        $backlogThreshold = (int) ($this->option('backlog-threshold') ?? config('health.queue_backlog_threshold'));

        $failed = (int) ($checks['queue']['failed'] ?? 0);
        $pending = (int) ($checks['queue']['pending'] ?? 0);

        if ($failed >= $failThreshold) {
            Log::warning('Failed-job count is high', ['failed' => $failed, 'threshold' => $failThreshold]);
        }

        if ($pending >= $backlogThreshold) {
            Log::warning('Queue backlog is high', ['pending' => $pending, 'threshold' => $backlogThreshold]);
        }

        $this->line((string) json_encode(['status' => $result['status'], 'checks' => $checks]));

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
