<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule task reset to run daily at 12:00 AM (midnight)
Schedule::command('tasks:reset-daily')
    ->dailyAt('00:00')
    ->name('reset-daily-tasks')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule task assignment to run daily at 6:00 AM
Schedule::command('tasks:assign-daily')
    ->dailyAt('06:00')
    ->name('assign-daily-tasks')
    ->withoutOverlapping()
    ->runInBackground();

// Alternative: Schedule using job dispatch
Schedule::call(function () {
    \App\Jobs\AssignDailyTasksJob::dispatch();
})->dailyAt('06:00')->name('assign-daily-tasks-job');
