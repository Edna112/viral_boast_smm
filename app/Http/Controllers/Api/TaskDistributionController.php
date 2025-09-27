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
     * Main task distribution algorithm - single route that handles everything
     */
    public function distributeTasks(): JsonResponse
    {
        try {
            $result = $this->distributionService->distributeTasksToUsers();

            // Get the tasks that were distributed
            $distributedTasks = [];
            if ($result['success'] && $result['distributed_tasks'] > 0) {
                $assignments = \App\Models\TaskAssignment::whereDate('created_at', today())
                    ->orderBy('created_at', 'desc')
                    ->with('task')
                    ->get();
                
                $distributedTasks = $assignments->map(function($assignment) {
                    return [
                        'assignment_id' => $assignment->id,
                        'task_id' => $assignment->task_id,
                        'user_uuid' => $assignment->user_uuid,
                        'title' => $assignment->task->title,
                        'description' => $assignment->task->description,
                        'reward' => $assignment->task->reward,
                        'platform' => $assignment->task->platform,
                        'task_type' => $assignment->task->task_type,
                        'assigned_at' => $assignment->assigned_at,
                        'due_date' => $assignment->due_date,
                        'status' => $assignment->status
                    ];
                })->toArray();
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] 
                    ? 'Task distribution algorithm completed successfully' 
                    : 'Task distribution algorithm failed',
                'data' => [
                    'tasks' => $distributedTasks,
                    'algorithm_results' => [
                        'distributed_tasks' => $result['distributed_tasks'],
                        'total_users_processed' => $result['total_users'],
                        'memberships_processed' => $result['memberships_processed'],
                        'distribution_errors' => $result['errors']
                    ],
                    'distribution_stats' => $this->distributionService->getDistributionStats(),
                    'available_tasks' => Task::getAvailableForDistribution()->count(),
                    'algorithm_checks' => [
                        'membership_validation' => 'completed',
                        'user_activity_check' => 'completed',
                        'daily_limit_verification' => 'completed',
                        'task_threshold_validation' => 'completed',
                        'distribution_priority_processing' => 'completed'
                    ]
                ]
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task distribution algorithm failed',
                'error' => $e->getMessage(),
                'data' => [
                    'tasks' => [],
                    'algorithm_checks' => [
                        'membership_validation' => 'failed',
                        'user_activity_check' => 'failed',
                        'daily_limit_verification' => 'failed',
                        'task_threshold_validation' => 'failed',
                        'distribution_priority_processing' => 'failed'
                    ]
                ]
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