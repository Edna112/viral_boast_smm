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
        $schedule->call(function () {
            $service = app(\App\Services\TaskDistributionService::class);
            $result = $service->assignDailyTasksToAllUsers();
            
            // Log the results
            \Log::info('Daily task assignment completed', $result);
            
            // Cache the last run time
            cache()->put('last_task_assignment', now(), now()->addDay());
        })->dailyAt('00:00')
          ->timezone('UTC')
          ->withoutOverlapping()
          ->runInBackground();

        // Mark expired tasks at midnight
        $schedule->call(function () {
            $expiredCount = \App\Models\TaskAssignment::where('expires_at', '<', now())
                ->where('status', 'pending')
                ->update(['status' => 'expired']);
                
            \Log::info("Marked {$expiredCount} tasks as expired");
        })->dailyAt('00:00')
          ->timezone('UTC');
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
