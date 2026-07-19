<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('posts:publish-due')->everyMinute();
Schedule::command('posts:refresh-scores')->everyFifteenMinutes();
Schedule::command('uploads:prune')->hourly();
Schedule::command('ads:settle')->everyFifteenMinutes();
Schedule::command('briefs:generate')->dailyAt('06:00');
Schedule::command('tenders:sync')->hourly();
