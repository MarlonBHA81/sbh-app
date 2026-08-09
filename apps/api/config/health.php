<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Health thresholds
    |--------------------------------------------------------------------------
    |
    | Used by the scheduled `system:health` command to decide when a queue
    | condition is worth a warning in the logs (which the observability pipeline
    | can escalate). The /api/v1/health endpoint reports the raw numbers.
    |
    */

    // Warn once the failed_jobs table reaches this many rows.
    'failed_jobs_threshold' => (int) env('HEALTH_FAILED_JOBS_THRESHOLD', 25),

    // Warn once this many jobs are waiting in the queue backlog.
    'queue_backlog_threshold' => (int) env('HEALTH_QUEUE_BACKLOG_THRESHOLD', 1000),

];
