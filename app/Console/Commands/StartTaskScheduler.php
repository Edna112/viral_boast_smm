<?php

namespace App\Console\Commands;

use App\Jobs\AssignDailyTasksJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StartTaskScheduler extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:start-scheduler {--immediate : Start immediately instead of waiting for next scheduled time}';

    /**
     * The console command description.
     */
    protected $description = 'Start the self-scheduling task assignment system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Task Assignment Scheduler...');

        if ($this->option('immediate')) {
            // Start immediately
            $this->info('⚡ Starting task assignment immediately...');
            AssignDailyTasksJob::dispatch();
            $this->info('✅ Task assignment job dispatched immediately!');
        } else {
            // Calculate next run time (next 6:00 AM)
            $nextRun = $this->calculateNextRunTime();
            
            $this->info("⏰ Next task assignment scheduled for: {$nextRun->format('Y-m-d H:i:s')}");
            
            // Schedule the job
            AssignDailyTasksJob::dispatch()->delay($nextRun);
            
            $this->info('✅ Self-scheduling task system started!');
            $this->info('📝 The system will automatically reschedule itself every 24 hours.');
        }

        Log::info('Task scheduler started', [
            'immediate' => $this->option('immediate'),
            'next_run' => $this->option('immediate') ? 'immediate' : $nextRun->format('Y-m-d H:i:s')
        ]);

        return Command::SUCCESS;
    }

    /**
     * Calculate the next run time (6:00 AM)
     */
    private function calculateNextRunTime()
    {
        $now = now();
        $nextRun = $now->copy()->setTime(6, 0, 0);

        // If it's already past 6:00 AM today, schedule for tomorrow
        if ($now->greaterThan($nextRun)) {
            $nextRun->addDay();
        }

        return $nextRun;
    }
}
