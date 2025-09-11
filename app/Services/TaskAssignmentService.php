<?php

namespace App\Services;

use App\Models\User;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\UserMembership;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TaskAssignmentService
{
    /**
     * Assign daily tasks to all active users
     */
    public function assignDailyTasks(): array
    {
        $results = [
            'total_users' => 0,
            'users_assigned' => 0,
            'total_assignments' => 0,
            'errors' => []
        ];

        try {
            // Get all active users with their active memberships
            $users = User::with(['activeMembership'])
                        ->whereHas('memberships', function($query) {
                            $query->where('user_memberships.is_active', true)
                                  ->where('user_memberships.expires_at', '>', now());
                        })
                        ->get();

            $results['total_users'] = $users->count();

            // Get available tasks for today
            $availableTasks = Task::active()->get();

            if ($availableTasks->isEmpty()) {
                $results['errors'][] = 'No active tasks available for assignment';
                return $results;
            }

            foreach ($users as $user) {
                try {
                    $assignments = $this->assignTasksToUser($user, $availableTasks);
                    $results['users_assigned']++;
                    $results['total_assignments'] += $assignments;
                } catch (\Exception $e) {
                    $results['errors'][] = "Error assigning tasks to user {$user->id}: " . $e->getMessage();
                    Log::error("Task assignment error for user {$user->id}", [
                        'error' => $e->getMessage(),
                        'user' => $user->id
                    ]);
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = 'General error: ' . $e->getMessage();
            Log::error('Daily task assignment failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Assign tasks to a specific user based on their membership
     */
    public function assignTasksToUser(User $user, $availableTasks): int
    {
        $activeMembership = $user->activeMembership->first();
        
        if (!$activeMembership) {
            throw new \Exception('User has no active membership');
        }

        $membership = $activeMembership;
        $dailyLimit = $membership->tasks_per_day;

        // Check if user already has tasks assigned today
        $existingAssignments = TaskAssignment::where('user_id', $user->id)
                                           ->assignedToday()
                                           ->count();

        if ($existingAssignments >= $dailyLimit) {
            return 0; // User already has their daily limit
        }

        // All users get exactly 1 task daily
        $tasksToAssign = 1;
        $assignedCount = 0;

        // Assign 1 task to each user
        if ($availableTasks->count() > 0) {
            $task = $availableTasks->first(); // Get the first available task
            $this->createTaskAssignment($user, $task, $membership);
            $assignedCount = 1;
        }

        return $assignedCount;
    }

    /**
     * Create a task assignment for a user
     */
    private function createTaskAssignment(User $user, Task $task, $membership): TaskAssignment
    {
        // Use default values since the existing task table doesn't have these columns
        $basePoints = 10; // Default base points
        $vipMultiplier = $membership->benefits ?? 1.0; // Use benefits as multiplier
        $finalReward = (int) round($basePoints * $vipMultiplier);

        return TaskAssignment::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'assigned_at' => now(),
            'expires_at' => now()->addDay(),
            'status' => 'pending',
            'base_points' => $basePoints,
            'vip_multiplier' => $vipMultiplier,
            'final_reward' => $finalReward,
        ]);
    }

    /**
     * Reset daily task assignments (mark expired tasks as expired)
     */
    public function resetDailyTasks(): array
    {
        $results = [
            'expired_assignments' => 0,
            'reset_users' => 0,
            'errors' => []
        ];

        try {
            // Mark expired assignments as expired
            $expiredCount = TaskAssignment::pending()
                                        ->where('expires_at', '<', now())
                                        ->update(['status' => 'expired']);

            $results['expired_assignments'] = $expiredCount;

            // Reset daily counters for users
            $resetUsers = UserMembership::where('last_reset_date', '!=', today())
                                      ->orWhereNull('last_reset_date')
                                      ->get();

            foreach ($resetUsers as $userMembership) {
                $userMembership->resetDailyTasks();
                $results['reset_users']++;
            }

            // Reset user daily counters
            User::where('last_task_reset_date', '!=', today())
                ->orWhereNull('last_task_reset_date')
                ->update([
                    'tasks_completed_today' => 0,
                    'last_task_reset_date' => today(),
                ]);

        } catch (\Exception $e) {
            $results['errors'][] = 'Reset error: ' . $e->getMessage();
            Log::error('Daily task reset failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Get user's current task assignments
     */
    public function getUserTasks(User $user): array
    {
        $assignments = TaskAssignment::with('task')
                                   ->where('user_id', $user->id)
                                   ->where('status', 'pending')
                                   ->where('expires_at', '>', now())
                                   ->get();

        return $assignments->map(function ($assignment) {
            return $assignment->getDetails();
        })->toArray();
    }

    /**
     * Get task statistics
     */
    public function getTaskStats(): array
    {
        $today = today();
        
        return [
            'total_assignments_today' => TaskAssignment::assignedToday()->count(),
            'completed_today' => TaskAssignment::completed()
                                             ->whereDate('completed_at', $today)
                                             ->count(),
            'pending_today' => TaskAssignment::pending()
                                           ->assignedToday()
                                           ->count(),
            'expired_today' => TaskAssignment::expired()
                                           ->whereDate('expires_at', $today)
                                           ->count(),
            'active_users' => User::whereHas('memberships', function($query) {
                $query->wherePivot('is_active', true)
                      ->wherePivot('expires_at', '>', now());
            })->count(),
        ];
    }
}
