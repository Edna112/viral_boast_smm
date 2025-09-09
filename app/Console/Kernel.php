<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run daily task assignment at midnight every day
        $schedule->command('tasks:assign-daily')
                 ->dailyAt('00:00')
                 ->timezone('UTC')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Optional: Run task reset every hour to catch any missed resets
        $schedule->command('tasks:assign-daily')
                 ->hourly()
                 ->when(function () {
                     // Only run if it's been more than 23 hours since last run
                     $lastRun = cache()->get('last_task_assignment');
                     return !$lastRun || now()->diffInHours($lastRun) >= 23;
                 })
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
