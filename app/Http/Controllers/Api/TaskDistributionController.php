<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskDistributionService;
use App\Services\EnhancedTaskAssignmentService;
use App\Models\Task;
use App\Models\Membership;
use App\Models\LastRequested;
use Carbon\Carbon;
use App\Models\User;
use App\Models\TaskHistory;
use App\Models\UserDailyTaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TaskDistributionController extends Controller
{
    protected $distributionService;
    protected $enhancedService;

    public function __construct(TaskDistributionService $distributionService, EnhancedTaskAssignmentService $enhancedService)
    {
        $this->distributionService = $distributionService;
        $this->enhancedService = $enhancedService;
    }

    /**
     * User-specific task assignment - assigns tasks to the authenticated user
     * Smart daily assignment: automatically assigns new tasks after 11:59 PM
     */
    public function distributeTasks(Request $request): JsonResponse
    {
        try {
            // Get authenticated user from token
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                    'data' => []
                ], 401);
            }

            // Get user's membership to determine task allocation
            $membership = $user->membership;
            
            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has no active membership',
                    'data' => []
                ], 400);
            }

            // Day-based gating using LastRequested: only assign new tasks once per day per user
            $lastRequested = LastRequested::firstOrNew(['user_uuid' => $user->uuid]);
            $lastRequestDate = $lastRequested->last_requested_at
                ? Carbon::parse($lastRequested->last_requested_at)->toDateString()
                : null;
            $today = today()->toDateString();

            if ($lastRequested->exists && $lastRequestDate === $today) {
                // Same day: do not assign new tasks; return today's existing pending tasks
                $todayAssignment = UserDailyTaskAssignment::getTodayAssignment($user->uuid);
                $assignedTaskIds = $todayAssignment->assigned_task_ids ?? [];

                $userTasks = \App\Models\TaskAssignment::where('user_uuid', $user->uuid)
                    ->when(!empty($assignedTaskIds), function ($q) use ($assignedTaskIds) {
                        $q->whereIn('task_id', $assignedTaskIds);
                    })
                    ->where('status', 'pending')
                    ->with('task')
                    ->get()
                    ->map(function ($assignment) {
                        return [
                            'id' => $assignment->task->id,
                            'title' => $assignment->task->title,
                            'description' => $assignment->task->description,
                            'category' => $assignment->task->category,
                            'task_type' => $assignment->task->task_type,
                            'platform' => $assignment->task->platform,
                            'instructions' => $assignment->task->instructions,
                            'target_url' => $assignment->task->target_url,
                            'benefit' => $assignment->task->benefit,
                            'priority' => $assignment->task->priority,
                            'assignment_id' => $assignment->id,
                            'assigned_at' => $assignment->created_at,
                            'expires_at' => $assignment->expires_at
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'message' => 'Today\'s tasks retrieved (already assigned earlier today)',
                    'data' => $userTasks->toArray()
                ]);
            }

            // New day or first-time request: perform cleanup, then assign and update last request date
            $this->performDailyCleanupIfNeeded();

            $assignedTasks = $this->assignNewDailyTasks($user, $membership);

            // Update last request marker
            $lastRequested->user_uuid = $user->uuid;
            $lastRequested->last_requested_at = now();
            $lastRequested->save();

            return response()->json([
                'success' => true,
                'message' => 'New daily tasks assigned successfully',
                'data' => [
                    'assigned_tasks' => $assignedTasks,
                    'total_assigned' => count($assignedTasks),
                    'assignment_date' => $today
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task assignment failed',
                'error' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Assign new daily tasks to user
     */
    private function assignNewDailyTasks(User $user, Membership $membership): array
    {
        $tasksPerDay = $membership->tasks_per_day;
        
        // Get ALL tasks this specific user has EVER been assigned
        $userAssignedTaskIds = \App\Models\TaskAssignment::where('user_uuid', $user->uuid)
            ->pluck('task_id')
            ->toArray();

        // Get available tasks using centralized Task helper (uses <= completion and < distribution thresholds)
        $availableTasks = Task::getAvailableForDistribution(null, $userAssignedTaskIds)
            ->take($tasksPerDay);

        if ($availableTasks->isEmpty()) {
            \Log::info('No available tasks for user daily assignment', [
                'user_uuid' => $user->uuid,
                'tasks_per_day' => $tasksPerDay,
                'user_assigned_task_ids_count' => count($userAssignedTaskIds)
            ]);
        }

        // Create task assignments for the user
        $assignedTasks = [];
        $assignedTaskIds = [];
        
        foreach ($availableTasks as $task) {
            $assignment = \App\Models\TaskAssignment::create([
                'user_uuid' => $user->uuid,
                'task_id' => $task->id,
                'status' => 'pending',
                'assigned_at' => now(),
                'expires_at' => now()->endOfDay(), // Expires at 11:59 PM
                'base_points' => $task->benefit,
                'vip_multiplier' => 1.0,
                'final_reward' => $task->benefit
            ]);

            // Increment task distribution count
            $task->increment('task_distribution_count');
            $assignedTaskIds[] = $task->id;

            $assignedTasks[] = [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'category' => $task->category,
                'task_type' => $task->task_type,
                'platform' => $task->platform,
                'instructions' => $task->instructions,
                'target_url' => $task->target_url,
                'benefit' => $task->benefit,
                'priority' => $task->priority,
                'assignment_id' => $assignment->id,
                'assigned_at' => $assignment->created_at,
                'expires_at' => $assignment->expires_at
            ];
        }

        // Record today's assignment in UserDailyTaskAssignment
        UserDailyTaskAssignment::createOrUpdateToday(
            $user->uuid, 
            $assignedTaskIds, 
            $membership->id, 
            $membership->tasks_per_day
        );

        return $assignedTasks;
    }

    /**
     * Distribute tasks to a specific user
     */
    public function distributeTasksToUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_uuid' => 'required|string|exists:users,uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user = User::where('uuid', $request->input('user_uuid'))->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $result = $this->distributionService->distributeTasksToUser($user);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] 
                    ? 'Tasks distributed to user successfully' 
                    : 'Failed to distribute tasks to user',
                'data' => [
                    'user_uuid' => $user->uuid,
                    'user_name' => $user->name,
                    'distributed_tasks' => $result['distributed_tasks'],
                    'errors' => $result['errors']
                ]
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task distribution failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get distribution statistics
     */
    public function getDistributionStats(): JsonResponse
    {
        try {
            $stats = $this->distributionService->getDistributionStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get distribution statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available tasks for distribution
     */
    public function getAvailableTasks(Request $request): JsonResponse
    {
        try {
            $categoryId = $request->input('category_id');
            $tasks = Task::getAvailableForDistribution($categoryId);

            $taskData = $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'reward' => $task->reward,
                    'category_id' => $task->category_id,
                    'platform' => $task->platform,
                    'distribution_count' => $task->task_distribution_count,
                    'completion_count' => $task->task_completion_count,
                    'distribution_threshold' => $task->distribution_threshold,
                    'completion_threshold' => $task->completion_threshold,
                    'can_be_distributed' => $task->canBeDistributed(),
                    'stats' => $task->getStats()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tasks' => $taskData,
                    'total_count' => $tasks->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get available tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get eligible users for task distribution
     */
    public function getEligibleUsers(Request $request): JsonResponse
    {
        try {
            $membershipId = $request->input('membership_id');
            
            if ($membershipId) {
                $membership = Membership::find($membershipId);
                if (!$membership) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Membership not found'
                    ], 404);
                }
                $eligibleUsers = $membership->getEligibleUsersForDistribution();
            } else {
                // Get eligible users from all memberships
                $eligibleUsers = collect();
                $memberships = Membership::where('is_active', true)->get();
                
                foreach ($memberships as $membership) {
                    $membershipUsers = $membership->getEligibleUsersForDistribution();
                    $eligibleUsers = $eligibleUsers->merge($membershipUsers);
                }
                
                // Remove duplicates
                $eligibleUsers = $eligibleUsers->unique('uuid');
            }

            $userData = $eligibleUsers->map(function ($user) {
                $activeMembership = $user->memberships()
                    ->where('is_active', true)
                    ->where(function($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->first();

                return [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'last_activity_at' => $user->last_activity_at,
                    'active_membership' => $activeMembership ? [
                        'name' => $activeMembership->membership_name,
                        'membership_icon' => $activeMembership->membership_icon,
                        'daily_task_limit' => $activeMembership->getDailyTaskLimit(),
                        'max_tasks_per_distribution' => $activeMembership->getMaxTasksPerDistribution()
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $userData,
                    'total_count' => $eligibleUsers->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get eligible users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task distribution thresholds
     */
    public function updateTaskThresholds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer|exists:tasks,id',
            'distribution_threshold' => 'required|integer|min:1',
            'completion_threshold' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $task = Task::find($request->input('task_id'));
            
            $task->update([
                'distribution_threshold' => $request->input('distribution_threshold'),
                'completion_threshold' => $request->input('completion_threshold')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task thresholds updated successfully',
                'data' => [
                    'task_id' => $task->id,
                    'distribution_threshold' => $task->distribution_threshold,
                    'completion_threshold' => $task->completion_threshold
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task thresholds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset task distribution counts
     */
    public function resetDistributionCounts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'nullable|integer|exists:tasks,id',
            'reset_all' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            if ($request->input('reset_all')) {
                // Reset all tasks
                Task::query()->update(['task_distribution_count' => 0]);
                $message = 'All task distribution counts reset successfully';
            } else {
                // Reset specific task
                $task = Task::find($request->input('task_id'));
                $task->update(['task_distribution_count' => 0]);
                $message = "Distribution count reset for task {$task->id}";
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset distribution counts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform daily cleanup if needed (first request of the day)
     * Archives all task submissions to task_history table
     */
    private function performDailyCleanupIfNeeded(): void
    {
        try {
            // Check if cleanup has already been done today
            $lastCleanupDate = \Cache::get('last_daily_cleanup_date');
            $today = now()->toDateString();
            
            if ($lastCleanupDate === $today) {
                // Cleanup already done today, skip
                return;
            }
            
            // Check if there are any submissions to archive
            $submissionCount = \App\Models\TaskSubmission::count();
            
            if ($submissionCount === 0) {
                // No submissions to archive, just mark cleanup as done
                \Cache::put('last_daily_cleanup_date', $today, now()->addDay());
                return;
            }
            
            // Perform the archive operation
            $archiveResults = TaskHistory::archiveSubmissions();
            
            // Log the cleanup operation
            \Log::info('Daily task submission cleanup completed', [
                'date' => $today,
                'archived_count' => $archiveResults['archived_count'],
                'errors' => $archiveResults['errors']
            ]);
            
            // Mark cleanup as completed for today
            \Cache::put('last_daily_cleanup_date', $today, now()->addDay());
            
            // Log success message
            if ($archiveResults['archived_count'] > 0) {
                \Log::info("Archived {$archiveResults['archived_count']} task submissions to history table");
            }
            
        } catch (\Exception $e) {
            \Log::error('Daily cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Enhanced daily task assignment for all users
     * Uses the new algorithm with precise requirements
     */
    public function assignDailyTasksEnhanced(): JsonResponse
    {
        try {
            $results = $this->enhancedService->assignDailyTasks();

            return response()->json([
                'success' => true,
                'message' => 'Enhanced daily task assignment completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enhanced daily task assignment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign tasks to a specific user using enhanced algorithm
     */
    public function assignTasksToUserEnhanced(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $assignedCount = $this->enhancedService->assignTasksToUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Tasks assigned successfully',
                'data' => [
                    'user_uuid' => $user->uuid,
                    'assigned_tasks' => $assignedCount,
                    'assignment_date' => today()->toDateString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task assignment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign tasks to new user using enhanced algorithm
     */
    public function assignTasksToNewUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_uuid' => 'required|string|exists:users,uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('uuid', $request->user_uuid)->first();
            $result = $this->enhancedService->assignTasksToNewUser($user);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Tasks assigned to new user' : 'Failed to assign tasks',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'New user task assignment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's task assignment status
     */
    public function getUserTaskStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $status = $this->enhancedService->getUserTaskStatus($user);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user task status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset daily task assignments
     */
    public function resetDailyAssignments(): JsonResponse
    {
        try {
            $results = $this->enhancedService->resetDailyAssignments();

            return response()->json([
                'success' => true,
                'message' => 'Daily assignments reset successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset daily assignments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enhanced task distribution statistics
     */
    public function getEnhancedDistributionStats(): JsonResponse
    {
        try {
            $stats = $this->enhancedService->getDistributionStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get distribution stats: ' . $e->getMessage()
            ], 500);
        }
    }
}