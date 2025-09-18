<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schedule;

class CheckSchedulerStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'scheduler:status';

    /**
     * The console command description.
     */
    protected $description = 'Check the status of scheduled tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📅 Laravel Task Scheduler Status');
        $this->line('');

        // Get all scheduled events
        $events = Schedule::events();
        
        if (empty($events)) {
            $this->warn('No scheduled events found.');
            return Command::SUCCESS;
        }

        $this->info('Scheduled Events:');
        $this->line('');

        foreach ($events as $event) {
            $description = $event->description ?: 'No description';
            $expression = $event->expression;
            $nextRun = $event->nextRunDate();
            
            $this->line("📋 {$description}");
            $this->line("   Cron: {$expression}");
            $this->line("   Next Run: {$nextRun->format('Y-m-d H:i:s')}");
            $this->line("   Command: {$event->command}");
            $this->line('');
        }

        $this->info('💡 To run the scheduler: php artisan schedule:run');
        $this->info('💡 To start scheduler daemon: php artisan schedule:work');

        return Command::SUCCESS;
    }
}
