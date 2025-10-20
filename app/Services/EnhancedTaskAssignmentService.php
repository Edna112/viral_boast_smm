<?php

namespace App\Services;

use App\Models\User;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\UserMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EnhancedTaskAssignmentService
{
    /**
     * Assign daily tasks to all active users
     * Tasks are assigned daily starting from 00:00 am onwards
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
            DB::beginTransaction();

            // Get all active users with their active memberships
            $users = User::with(['memberships' => function($query) {
                $query->where('user_memberships.is_active', true)
                      ->where(function($q) {
                          $q->whereNull('user_memberships.expires_at')
                            ->orWhere('user_memberships.expires_at', '>', now());
                      });
            }])
            ->whereHas('memberships', function($query) {
                $query->where('user_memberships.is_active', true)
                      ->where(function($q) {
                          $q->whereNull('user_memberships.expires_at')
                            ->orWhere('user_memberships.expires_at', '>', now());
                      });
            })
            ->get();

            $results['total_users'] = $users->count();

            foreach ($users as $user) {
                try {
                    $assignments = $this->assignTasksToUser($user);
                    if ($assignments > 0) {
                        $results['users_assigned']++;
                        $results['total_assignments'] += $assignments;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Error assigning tasks to user {$user->uuid}: " . $e->getMessage();
                    Log::error("Task assignment error for user {$user->uuid}", [
                        'error' => $e->getMessage(),
                        'user' => $user->uuid
                    ]);
                }
            }

            DB::commit();
            Log::info('Enhanced daily task assignment completed', $results);

        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = 'Daily assignment failed: ' . $e->getMessage();
            
            Log::error('Enhanced daily task assignment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    /**
     * Assign tasks to a specific user based on their membership level
     * Any task request made after midnight on a given day should be considered a request for new tasks of that day
     */
    public function assignTasksToUser(User $user): int
    {
        // Get user's active membership
        $userMembership = $user->memberships()
            ->where('user_memberships.is_active', true)
            ->where(function($query) {
                $query->whereNull('user_memberships.expires_at')
                      ->orWhere('user_memberships.expires_at', '>', now());
            })
            ->orderBy('membership.id', 'desc')
            ->first();

        if (!$userMembership) {
            throw new \Exception('User has no active membership');
        }

        $membership = $userMembership->membership;
        if (!$membership) {
            throw new \Exception('User membership data is invalid');
        }

        $tasksPerDay = $membership->tasks_per_day;

        // Check if user already has tasks assigned today
        $existingTasksToday = TaskAssignment::where('user_uuid', $user->uuid)
            ->whereDate('assigned_at', today())
            ->where('status', 'pending')
            ->count();

        if ($existingTasksToday >= $tasksPerDay) {
            return 0; // User already has their daily quota
        }

        // Calculate how many tasks user needs
        $tasksNeeded = $tasksPerDay - $existingTasksToday;

        // Get tasks that have not previously been assigned to this user
        $userAssignedTaskIds = TaskAssignment::where('user_uuid', $user->uuid)
            ->pluck('task_id')
            ->toArray();

        // Get available tasks based on the enhanced criteria
        $availableTasks = $this->getAvailableTasksForUser($userAssignedTaskIds, $tasksNeeded);

        $assignedCount = 0;
        foreach ($availableTasks as $task) {
            $assignment = $this->createTaskAssignment($user, $task);
            
            if ($assignment) {
                // Increment task distribution count
                $task->increment('task_distribution_count');
                $assignedCount++;
            }
        }

        return $assignedCount;
    }

    /**
     * Get available tasks for user assignment based on enhanced criteria
     * Only tasks with task_completion_count less than or equal to their threshold should be available for assignment
     */
    private function getAvailableTasksForUser(array $userAssignedTaskIds, int $limit): \Illuminate\Database\Eloquent\Collection
    {
        return Task::where('is_active', true)
            ->where('task_status', 'active')
            // Task-level threshold: only tasks with completion count <= threshold
            ->whereRaw('task_completion_count <= threshold_value')
            // Task-level threshold: only tasks with distribution count < threshold
            ->whereRaw('task_distribution_count < threshold_value')
            // User-level: never assigned to THIS user before
            ->whereNotIn('id', $userAssignedTaskIds)
            ->orderByRaw("CASE priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
                END")
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a task assignment for a user
     */
    private function createTaskAssignment(User $user, Task $task): ?TaskAssignment
    {
        try {
            $assignment = TaskAssignment::create([
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'status' => 'pending',
                'assigned_at' => now(),
                'expires_at' => now()->endOfDay(), // Expires at 11:59 PM
                'base_points' => $task->benefit,
                'vip_multiplier' => 1.0,
                'final_reward' => $task->benefit
            ]);

            Log::info('Task assigned to user', [
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'assignment_id' => $assignment->id
            ]);

            return $assignment;

        } catch (\Exception $e) {
            Log::error('Failed to create task assignment', [
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Assign tasks to new users registered during the day
     * Tasks assigned to new users at any time during the day should be based on the current day's criteria
     */
    public function assignTasksToNewUser(User $user): array
    {
        $result = [
            'success' => false,
            'assigned_tasks' => 0,
            'errors' => []
        ];

        try {
            $assignedCount = $this->assignTasksToUser($user);
            
            $result['success'] = true;
            $result['assigned_tasks'] = $assignedCount;

            Log::info('Tasks assigned to new user', [
                'user_uuid' => $user->uuid,
                'assigned_count' => $assignedCount
            ]);

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            
            Log::error('Failed to assign tasks to new user', [
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Get user's task assignment status for the current day
     */
    public function getUserTaskStatus(User $user): array
    {
        $userMembership = $user->memberships()
            ->where('user_memberships.is_active', true)
            ->where(function($query) {
                $query->whereNull('user_memberships.expires_at')
                      ->orWhere('user_memberships.expires_at', '>', now());
            })
            ->orderBy('membership.id', 'desc')
            ->first();

        if (!$userMembership) {
            return [
                'has_membership' => false,
                'tasks_per_day' => 0,
                'assigned_today' => 0,
                'remaining_today' => 0
            ];
        }

        $membership = $userMembership->membership;
        if (!$membership) {
            return [
                'has_membership' => false,
                'tasks_per_day' => 0,
                'assigned_today' => 0,
                'remaining_today' => 0
            ];
        }

        $tasksPerDay = $membership->tasks_per_day;

        $assignedToday = TaskAssignment::where('user_uuid', $user->uuid)
            ->whereDate('assigned_at', today())
            ->where('status', 'pending')
            ->count();

        return [
            'has_membership' => true,
            'membership_name' => $membership->membership_name,
            'tasks_per_day' => $tasksPerDay,
            'assigned_today' => $assignedToday,
            'remaining_today' => max(0, $tasksPerDay - $assignedToday),
            'can_receive_tasks' => $assignedToday < $tasksPerDay
        ];
    }

    /**
     * Reset daily task assignments (run at midnight)
     */
    public function resetDailyAssignments(): array
    {
        $results = [
            'expired_assignments' => 0,
            'errors' => []
        ];

        try {
            // Mark expired assignments
            $expiredCount = TaskAssignment::where('status', 'pending')
                ->where('expires_at', '<', now())
                ->update(['status' => 'expired']);

            $results['expired_assignments'] = $expiredCount;

            Log::info('Daily task assignments reset', [
                'expired_assignments' => $expiredCount
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            
            Log::error('Failed to reset daily assignments', [
                'error' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Get task distribution statistics
     */
    public function getDistributionStats(): array
    {
        $totalTasks = Task::count();
        $activeTasks = Task::where('is_active', true)->count();
        $availableTasks = Task::where('is_active', true)
            ->whereRaw('task_completion_count <= threshold_value')
            ->whereRaw('task_distribution_count < threshold_value')
            ->count();

        $totalAssignments = TaskAssignment::count();
        $pendingAssignments = TaskAssignment::where('status', 'pending')->count();
        $completedAssignments = TaskAssignment::where('status', 'completed')->count();

        return [
            'tasks' => [
                'total' => $totalTasks,
                'active' => $activeTasks,
                'available_for_distribution' => $availableTasks
            ],
            'assignments' => [
                'total' => $totalAssignments,
                'pending' => $pendingAssignments,
                'completed' => $completedAssignments
            ],
            'distribution_efficiency' => $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 2) : 0
        ];
    }
}
