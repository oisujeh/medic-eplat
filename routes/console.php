<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The data-quality pass runs once a night, after the day's work is in and
 * before the morning queues open. Needs the scheduler running on the server:
 * `php artisan schedule:work`, or cron calling `schedule:run` every minute.
 */
Schedule::command('data-quality:sweep')
    ->dailyAt('01:30')
    ->withoutOverlapping();
