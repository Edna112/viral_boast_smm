<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskDistributionService
{
    /**
     * Distribute tasks to users based on their membership
     */
    public function distributeTasksToUsers(): array
    {
        $results = [
            'success' => true,
            'distributed_tasks' => 0,
            'total_users' => 0,
            'memberships_processed' => 0,
            'errors' => []
        ];

        try {
            DB::beginTransaction();

            // Get all active memberships ordered by priority
            $memberships = Membership::where('is_active', true)
                ->orderBy('price', 'desc')
                ->get();

            foreach ($memberships as $membership) {
                $membershipResult = $this->distributeTasksForMembership($membership);
                
                $results['distributed_tasks'] += $membershipResult['distributed_tasks'];
                $results['total_users'] += $membershipResult['users_processed'];
                $results['memberships_processed']++;
                
                if (!empty($membershipResult['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $membershipResult['errors']);
                }
            }

            DB::commit();
            
            Log::info('Task distribution completed', [
                'distributed_tasks' => $results['distributed_tasks'],
                'total_users' => $results['total_users'],
                'memberships_processed' => $results['memberships_processed']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $results['success'] = false;
            $results['errors'][] = 'Distribution failed: ' . $e->getMessage();
            
            Log::error('Task distribution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    /**
     * Distribute tasks for a specific membership
     */
    public function distributeTasksForMembership(Membership $membership): array
    {
        $result = [
            'distributed_tasks' => 0,
            'users_processed' => 0,
            'errors' => []
        ];

        try {
            // Get eligible users for this membership
            $eligibleUsers = $membership->getEligibleUsersForDistribution();
            
            if ($eligibleUsers->isEmpty()) {
                Log::info("No eligible users found for membership: {$membership->membership_name}");
                return $result;
            }

            // Get available tasks for distribution
            $availableTasks = Task::getAvailableForDistribution();
            
            if ($availableTasks->isEmpty()) {
                Log::info("No available tasks for distribution");
                return $result;
            }

            $maxTasksPerDistribution = $membership->getMaxTasksPerDistribution();
            $distributedCount = 0;

            foreach ($eligibleUsers as $user) {
                if ($distributedCount >= $maxTasksPerDistribution) {
                    break;
                }

                // Check if user has reached daily task limit
                if ($this->hasUserReachedDailyLimit($user, $membership)) {
                    continue;
                }

                // Get tasks for this user
                $userTasks = $this->getTasksForUser($user, $availableTasks, $membership);
                
                foreach ($userTasks as $task) {
                    if ($distributedCount >= $maxTasksPerDistribution) {
                        break;
                    }

                    // Create task assignment
                    $assignment = $this->createTaskAssignment($user, $task);
                    
                    if ($assignment) {
                        // Increment task distribution count
                        $task->incrementDistributionCount();
                        $distributedCount++;
                        $result['distributed_tasks']++;
                    }
                }

                $result['users_processed']++;
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Membership {$membership->membership_name}: " . $e->getMessage();
            Log::error("Task distribution failed for membership {$membership->membership_name}", [
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Check if user has reached daily task limit
     */
    private function hasUserReachedDailyLimit(User $user, Membership $membership): bool
    {
        $dailyLimit = $membership->getDailyTaskLimit();
        
        // Get user's membership pivot data
        $userMembership = $user->memberships()
            ->where('membership_id', $membership->id)
            ->where('is_active', true)
            ->first();

        if (!$userMembership) {
            return true; // No active membership
        }

        $pivot = $userMembership->pivot;
        $lastResetDate = $pivot->last_reset_date;
        $dailyTasksCompleted = $pivot->daily_tasks_completed ?? 0;

        // Check if we need to reset daily count
        if (!$lastResetDate || $lastResetDate < now()->toDateString()) {
            // Reset daily count
            $user->memberships()->updateExistingPivot($membership->id, [
                'daily_tasks_completed' => 0,
                'last_reset_date' => now()->toDateString()
            ]);
            return false;
        }

        return $dailyTasksCompleted >= $dailyLimit;
    }

    /**
     * Get tasks for a specific user
     */
    private function getTasksForUser(User $user, $availableTasks, Membership $membership): array
    {
        $userTasks = [];
        $maxTasks = min(3, $membership->getMaxTasksPerDistribution()); // Max 3 tasks per user per distribution

        // Filter tasks that user hasn't been assigned recently
        $recentlyAssignedTaskIds = TaskAssignment::where('user_uuid', $user->uuid)
            ->where('created_at', '>=', now()->subDays(1))
            ->pluck('task_id')
            ->toArray();

        foreach ($availableTasks as $task) {
            if (count($userTasks) >= $maxTasks) {
                break;
            }

            // Skip if user was recently assigned this task
            if (in_array($task->id, $recentlyAssignedTaskIds)) {
                continue;
            }

            // Check if task can be distributed
            if (!$task->canBeDistributed()) {
                continue;
            }

            $userTasks[] = $task;
        }

        return $userTasks;
    }

    /**
     * Create task assignment
     */
    private function createTaskAssignment(User $user, Task $task): ?TaskAssignment
    {
        try {
            $assignment = TaskAssignment::create([
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'status' => 'pending',
                'assigned_at' => now(),
                'due_date' => now()->addDays(1), // 24 hours to complete
            ]);

            Log::info("Task assigned to user", [
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'assignment_id' => $assignment->id
            ]);

            return $assignment;

        } catch (\Exception $e) {
            Log::error("Failed to create task assignment", [
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get distribution statistics
     */
    public function getDistributionStats(): array
    {
        $stats = [
            'total_tasks' => Task::count(),
            'available_tasks' => Task::getAvailableForDistribution()->count(),
            'distributed_today' => TaskAssignment::whereDate('created_at', today())->count(),
            'pending_assignments' => TaskAssignment::where('status', 'pending')->count(),
            'completed_assignments' => TaskAssignment::where('status', 'completed')->count(),
            'memberships' => []
        ];

        // Get stats per membership
        $memberships = Membership::where('is_active', true)->get();
        foreach ($memberships as $membership) {
            $eligibleUsers = $membership->getEligibleUsersForDistribution();
            
            $stats['memberships'][] = [
                'name' => $membership->membership_name,
                'eligible_users' => $eligibleUsers->count(),
                'daily_task_limit' => $membership->getDailyTaskLimit(),
                'max_tasks_per_distribution' => $membership->getMaxTasksPerDistribution(),
                'priority' => $membership->getDistributionPriority()
            ];
        }

        return $stats;
    }

    /**
     * Distribute tasks to a specific user
     */
    public function distributeTasksToUser(User $user): array
    {
        $result = [
            'success' => false,
            'distributed_tasks' => 0,
            'errors' => []
        ];

        try {
            // Get user's active membership
            $userMembership = $user->memberships()
                ->where('user_memberships.is_active', true)
                ->where(function($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->orderBy('priority_level', 'desc')
                ->first();

            if (!$userMembership) {
                $result['errors'][] = 'No active membership found for user';
                return $result;
            }

            $membership = $userMembership->membership;

            // Check if user has reached daily limit
            if ($this->hasUserReachedDailyLimit($user, $membership)) {
                $result['errors'][] = 'User has reached daily task limit';
                return $result;
            }

            // Get available tasks
            $availableTasks = Task::getAvailableForDistribution();
            
            if ($availableTasks->isEmpty()) {
                $result['errors'][] = 'No available tasks for distribution';
                return $result;
            }

            // Get tasks for user
            $userTasks = $this->getTasksForUser($user, $availableTasks, $membership);
            
            foreach ($userTasks as $task) {
                $assignment = $this->createTaskAssignment($user, $task);
                
                if ($assignment) {
                    $task->incrementDistributionCount();
                    $result['distributed_tasks']++;
                }
            }

            $result['success'] = true;

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            Log::error("Failed to distribute tasks to user {$user->uuid}", [
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Assign daily tasks to all active users (called by scheduler)
     */
    public function assignDailyTasksToAllUsers(): array
    {
        $results = [
            'users_processed' => 0,
            'tasks_assigned' => 0,
            'errors' => []
        ];

        try {
            DB::beginTransaction();

            // Get all active users with memberships
            $users = User::whereHas('memberships', function($query) {
                $query->where('user_memberships.is_active', true)
                      ->where(function($q) {
                          $q->whereNull('user_memberships.expires_at')
                            ->orWhere('user_memberships.expires_at', '>', now());
                      });
            })->get();

            foreach ($users as $user) {
                try {
                    $assignedCount = $this->assignDailyTasksToUser($user);
                    $results['users_processed']++;
                    $results['tasks_assigned'] += $assignedCount;
                } catch (\Exception $e) {
                    $results['errors'][] = "User {$user->uuid}: " . $e->getMessage();
                    Log::error("Failed to assign daily tasks to user {$user->uuid}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();
            
            Log::info('Daily task assignment completed', $results);

        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = 'Daily assignment failed: ' . $e->getMessage();
            
            Log::error('Daily task assignment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    /**
     * Assign daily tasks to a specific user
     */
    private function assignDailyTasksToUser(User $user): int
    {
        // Get user's active membership
        $membership = $user->membership;
        
        if (!$membership) {
            throw new \Exception('User has no active membership');
        }

        $tasksPerDay = $membership->tasks_per_day;
        
        // Check if user already has tasks assigned today
        $existingTasksToday = TaskAssignment::where('user_uuid', $user->uuid)
            ->whereDate('assigned_at', today())
            ->where('status', 'pending')
            ->count();

        if ($existingTasksToday >= $tasksPerDay) {
            return 0; // User already has their daily tasks
        }

        // Get ALL tasks this specific user has EVER been assigned
        $userAssignedTaskIds = TaskAssignment::where('user_uuid', $user->uuid)
            ->pluck('task_id')
            ->toArray();
        
        // Calculate how many tasks user needs
        $tasksNeeded = $tasksPerDay - $existingTasksToday;
        
        // Get available tasks with correct filtering
        $availableTasks = Task::where('is_active', true)
            ->where('task_status', 'active')
            ->whereRaw('task_completion_count < threshold_value') // Task-level: not completed globally
            ->whereRaw('task_distribution_count < threshold_value') // Task-level: not over-distributed globally
            ->whereNotIn('id', $userAssignedTaskIds) // User-level: never assigned to THIS user before
            ->orderByRaw("CASE priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
                END")
            ->orderBy('created_at', 'asc')
            ->limit($tasksNeeded)
            ->get();
        
        $assignedCount = 0;
        foreach ($availableTasks as $task) {
            TaskAssignment::create([
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'status' => 'pending',
                'assigned_at' => now(),
                'expires_at' => now()->endOfDay(), // Expires at 11:59 PM
                'base_points' => $task->benefit,
                'vip_multiplier' => 1.0,
                'final_reward' => $task->benefit
            ]);
            
            $task->increment('task_distribution_count');
            $assignedCount++;
        }
        
        return $assignedCount;
    }
}
