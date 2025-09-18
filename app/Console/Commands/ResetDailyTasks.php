<?php

namespace App\Console\Commands;

use App\Services\TaskAssignmentService;
use Illuminate\Console\Command;

class ResetDailyTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:reset-daily';

    /**
     * The console command description.
     */
    protected $description = 'Reset daily task counters and expire old assignments';

    protected $taskAssignmentService;

    public function __construct(TaskAssignmentService $taskAssignmentService)
    {
        parent::__construct();
        $this->taskAssignmentService = $taskAssignmentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily task reset...');

        $results = $this->taskAssignmentService->resetDailyTasks();

        $this->info("Expired assignments: {$results['expired_assignments']}");
        $this->info("Users reset: {$results['reset_users']}");

        if (!empty($results['errors'])) {
            $this->error('Reset errors:');
            foreach ($results['errors'] as $error) {
                $this->error("- {$error}");
            }
        }

        $this->info('Daily task reset completed!');
        
        return Command::SUCCESS;
    }
}
