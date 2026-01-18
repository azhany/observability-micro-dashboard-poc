<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule 1-minute metric rollups to run every minute
Schedule::command('metrics:downsample 1m')->everyMinute();

// Schedule 5-minute metric rollups to run every 5 minutes
Schedule::command('metrics:downsample 5m')->everyFiveMinutes();

// Schedule metric cleanup to run daily at 2 AM
Schedule::command('metrics:cleanup')->dailyAt('02:00');
