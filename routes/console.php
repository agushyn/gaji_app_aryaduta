<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule queue runner for shared hosting / cPanel cron execution
Schedule::command('payroll:process-queue --limit=30 --timeout=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
