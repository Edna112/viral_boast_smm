<?php

namespace App\Console\Commands;

use App\Services\TaskAssignmentService;
use Illuminate\Console\Command;

class AssignDailyTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:assign-daily';

    /**
     * The console command description.
     */
    protected $description = 'Assign daily tasks to all active users';

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
        $this->info('Starting daily task assignment...');

        // First reset expired tasks
        $this->info('Resetting expired tasks...');
        $resetResults = $this->taskAssignmentService->resetDailyTasks();
        
        $this->info("Expired assignments: {$resetResults['expired_assignments']}");
        $this->info("Users reset: {$resetResults['reset_users']}");

        if (!empty($resetResults['errors'])) {
            $this->error('Reset errors:');
            foreach ($resetResults['errors'] as $error) {
                $this->error("- {$error}");
            }
        }

        // Then assign new tasks
        $this->info('Assigning new tasks...');
        $assignmentResults = $this->taskAssignmentService->assignDailyTasks();

        $this->info("Total users: {$assignmentResults['total_users']}");
        $this->info("Users assigned: {$assignmentResults['users_assigned']}");
        $this->info("Total assignments: {$assignmentResults['total_assignments']}");

        if (!empty($assignmentResults['errors'])) {
            $this->error('Assignment errors:');
            foreach ($assignmentResults['errors'] as $error) {
                $this->error("- {$error}");
            }
        }

        $this->info('Daily task assignment completed!');
        
        return Command::SUCCESS;
    }
}
