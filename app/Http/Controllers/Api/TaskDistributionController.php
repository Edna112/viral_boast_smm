<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskDistributionService;
use App\Models\Task;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TaskDistributionController extends Controller
{
    protected $distributionService;

    public function __construct(TaskDistributionService $distributionService)
    {
        $this->distributionService = $distributionService;
    }

    /**
     * User-specific task assignment - assigns tasks to the authenticated user
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

            // Check if user already has tasks assigned today
            $existingTasksToday = \App\Models\TaskAssignment::where('user_uuid', $user->uuid)
                ->whereDate('created_at', today())
                ->where('status', 'pending')
                ->count();

            // If user already has their daily allocation, return existing tasks
            if ($existingTasksToday >= $membership->tasks_per_day) {
                $userTasks = \App\Models\TaskAssignment::where('user_uuid', $user->uuid)
                    ->whereDate('created_at', today())
                    ->where('status', 'pending')
                    ->with('task')
                    ->get()
                    ->map(function($assignment) {
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
                    'message' => 'User already has daily tasks assigned',
                    'data' => $userTasks->toArray()
                ]);
            }

            // Calculate how many more tasks user needs
            $tasksNeeded = $membership->tasks_per_day - $existingTasksToday;

            // Get tasks that user has never completed (to avoid re-assigning completed tasks)
            $userCompletedTaskIds = \App\Models\TaskAssignment::where('user_uuid', $user->uuid)
                ->where('status', 'completed')
                ->pluck('task_id')
                ->toArray();

            // Get available tasks (not completed by this user, not at threshold)
            $availableTasks = Task::where('is_active', true)
                ->where('task_status', 'active')
                ->whereRaw('task_completion_count < threshold_value')
                ->whereRaw('task_distribution_count < threshold_value')
                ->whereNotIn('id', $userCompletedTaskIds) // Exclude tasks user already completed
                ->orderByRaw("CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                    END")
                ->orderBy('created_at', 'asc')
                ->limit($tasksNeeded)
                ->get();

            // Create task assignments for the user
            $assignedTasks = [];
            $newTaskIds = []; // Track which tasks are being assigned for the first time to this user
            
            foreach ($availableTasks as $task) {
                // Check if this user has ever been assigned this task before
                $userHasBeenAssignedThisTask = \App\Models\TaskAssignment::where('user_uuid', $user->uuid)
                    ->where('task_id', $task->id)
                    ->exists();

                $assignment = \App\Models\TaskAssignment::create([
                    'user_uuid' => $user->uuid,
                    'task_id' => $task->id,
                    'status' => 'pending',
                    'assigned_at' => now(),
                    'expires_at' => now()->addDays(1), // 24 hours to complete
                    'base_points' => $task->benefit,
                    'vip_multiplier' => 1.0,
                    'final_reward' => $task->benefit
                ]);

                // Only increment task_distribution_count if this is the first time this user gets this task
                if (!$userHasBeenAssignedThisTask) {
                    $task->increment('task_distribution_count');
                    $newTaskIds[] = $task->id;
                }

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
                    'expires_at' => $assignment->expires_at,
                    'is_new_assignment' => !$userHasBeenAssignedThisTask // Track if this is new
                ];
            }

            // If we still need more tasks but none are available, get any remaining tasks
            if (count($assignedTasks) < $tasksNeeded) {
                $remainingNeeded = $tasksNeeded - count($assignedTasks);
                
                $additionalTasks = Task::where('is_active', true)
                    ->where('task_status', 'active')
                    ->whereRaw('task_completion_count < threshold_value')
                    ->whereNotIn('id', $userCompletedTaskIds) // Still exclude completed tasks
                    ->orderByRaw("CASE priority 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'medium' THEN 3 
                        WHEN 'low' THEN 4 
                        END")
                    ->limit($remainingNeeded)
                    ->get();

                foreach ($additionalTasks as $task) {
                    // Check if this user has ever been assigned this task before
                    $userHasBeenAssignedThisTask = \App\Models\TaskAssignment::where('user_uuid', $user->uuid)
                        ->where('task_id', $task->id)
                        ->exists();

                    $assignment = \App\Models\TaskAssignment::create([
                        'user_uuid' => $user->uuid,
                        'task_id' => $task->id,
                        'status' => 'pending',
                        'assigned_at' => now(),
                        'expires_at' => now()->addDays(1),
                        'base_points' => $task->benefit,
                        'vip_multiplier' => 1.0,
                        'final_reward' => $task->benefit
                    ]);

                    // Only increment task_distribution_count if this is the first time this user gets this task
                    if (!$userHasBeenAssignedThisTask) {
                        $task->increment('task_distribution_count');
                        $newTaskIds[] = $task->id;
                    }

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
                        'expires_at' => $assignment->expires_at,
                        'is_new_assignment' => !$userHasBeenAssignedThisTask
                    ];
                }
            }

            // Save assigned tasks to user's assigned_tasks field
            if (!empty($assignedTasks)) {
                $user->addAssignedTasks($assignedTasks);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tasks assigned successfully',
                'data' => [
                    'assigned_tasks' => $assignedTasks,
                    'user_assigned_tasks_count' => $user->getAssignedTasksCount(),
                    'total_assigned_today' => count($assignedTasks),
                    'new_task_assignments' => count($newTaskIds),
                    'reassigned_tasks' => count($assignedTasks) - count($newTaskIds),
                    'tasks_with_increased_distribution_count' => $newTaskIds
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
}