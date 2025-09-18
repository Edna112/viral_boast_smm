<?php

namespace App\Jobs;

use App\Services\TaskAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AssignDailyTasksJob implements ShouldQueue
{
    use Queueable;

    protected $taskAssignmentService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->taskAssignmentService = app(TaskAssignmentService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting daily task assignment job...');

        try {
            // Reset expired tasks first
            $resetResults = $this->taskAssignmentService->resetDailyTasks();
            Log::info('Task reset completed', $resetResults);

            // Assign new tasks
            $assignmentResults = $this->taskAssignmentService->assignDailyTasks();
            Log::info('Task assignment completed', $assignmentResults);

            Log::info('Daily task assignment job completed successfully');

        } catch (\Exception $e) {
            Log::error('Daily task assignment job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw the exception to mark job as failed
            throw $e;
        }
    }

}
