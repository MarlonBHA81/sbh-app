<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// withoutOverlapping() on every entry: a run that outlives its own interval
// (a slow tenders:sync, publish-due under load) would otherwise start a second
// copy alongside the first and double-process the same rows. onOneServer() is
// deliberately NOT set — it requires a shared lock store and there is a single
// scheduler process today; add it before scaling the scheduler horizontally.
Schedule::command('posts:publish-due')->everyMinute()->withoutOverlapping();
Schedule::command('posts:refresh-scores')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('uploads:prune')->hourly()->withoutOverlapping();
Schedule::command('ads:settle')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('briefs:generate')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('tenders:sync')->hourly()->withoutOverlapping();

// Replays receipts that failed after a payment was recorded, and surfaces
// orders left pending past the checkout window for manual PayFast review.
Schedule::command('shop:reconcile-orders')->everyThirtyMinutes()->withoutOverlapping();

// Bearer tokens now expire (config/sanctum.php). Expired rows stop working
// immediately but linger in the table; drop them once they're well past use so
// the token list a user sees stays meaningful.
Schedule::command('sanctum:prune-expired --hours=168')->daily()->withoutOverlapping();

// Deep dependency health probe: logs a warning (which observability can
// escalate) when a dependency is down or the queue backlog / failed-job count
// crosses its threshold.
Schedule::command('system:health')->everyFiveMinutes()->withoutOverlapping();
