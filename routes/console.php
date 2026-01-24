<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\HealthCheckListingsJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Schedule the Health Check Bot to run every 60 minutes.
 * This checks all listed projects for website health issues.
 */
Schedule::job(new HealthCheckListingsJob())
    ->hourly()
    ->name('health-check-listings')
    ->withoutOverlapping()
    ->onOneServer();
