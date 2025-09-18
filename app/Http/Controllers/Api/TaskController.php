<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskCategory;
use App\Services\TaskAssignmentService;
use App\Services\TaskCompletionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    protected $taskAssignmentService;
    protected $taskCompletionService;

    public function __construct(
        TaskAssignmentService $taskAssignmentService,
        TaskCompletionService $taskCompletionService
    ) {
        $this->taskAssignmentService = $taskAssignmentService;
        $this->taskCompletionService = $taskCompletionService;
    }

    /**
     * Get user's current task assignments
     */
    public function getUserTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $tasks = $this->taskAssignmentService->getUserTasks($user);

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $tasks,
                'total_tasks' => count($tasks),
                'user_membership' => $user->activeMembership?->getDetails(),
            ]
        ]);
    }

    /**
     * Complete a task assignment
     */
    public function completeTask(Request $request, int $assignmentId): JsonResponse
    {
        $request->validate([
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        $photo = $request->file('photo');
        $result = $this->taskCompletionService->completeTask($assignmentId, $photo);

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * Get user's task completion history
     */
    public function getCompletionHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->get('limit', 50);
        
        $history = $this->taskCompletionService->getUserCompletionHistory($user, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'history' => $history,
                'total_records' => count($history),
            ]
        ]);
    }

    /**
     * Get user's task statistics
     */
    public function getUserStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->taskCompletionService->getUserStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get task details
     */
    public function getTaskDetails(Request $request, int $assignmentId): JsonResponse
    {
        $assignment = TaskAssignment::with(['task', 'user'])
                                  ->where('user_id', $request->user()->id)
                                  ->find($assignmentId);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Task assignment not found',
                'error' => 'AssignmentNotFound'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment->getDetails()
        ]);
    }

    /**
     * Get available task categories
     */
    public function getCategories(): JsonResponse
    {
        $categories = \App\Models\TaskCategory::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'total_categories' => $categories->count(),
            ]
        ]);
    }

    /**
     * Get available tasks (for admin or preview)
     */
    public function getAvailableTasks(Request $request): JsonResponse
    {
        $tasks = Task::active()
                    ->ordered()
                    ->get()
                    ->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'title' => $task->title,
                            'description' => $task->description,
                            'platform' => $task->platform,
                            'reward' => $task->reward,
                            'estimated_duration_minutes' => $task->estimated_duration_minutes,
                            'requires_photo' => $task->requires_photo,
                            'instructions' => $task->instructions,
                            'target_url' => $task->target_url,
                            'threshold_value' => $task->threshold_value,
                        ];
                    });

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $tasks,
                'total_tasks' => $tasks->count(),
            ]
        ]);
    }

    /**
     * Get task statistics (admin only)
     */
    public function getTaskStats(Request $request): JsonResponse
    {
        $stats = $this->taskAssignmentService->getTaskStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Manually assign daily tasks (admin only)
     */
    public function assignDailyTasks(Request $request): JsonResponse
    {
        $results = $this->taskAssignmentService->assignDailyTasks();

        return response()->json([
            'success' => true,
            'message' => 'Daily tasks assigned successfully',
            'data' => $results
        ]);
    }

    /**
     * Reset daily tasks (admin only)
     */
    public function resetDailyTasks(Request $request): JsonResponse
    {
        $results = $this->taskAssignmentService->resetDailyTasks();

        return response()->json([
            'success' => true,
            'message' => 'Daily tasks reset successfully',
            'data' => $results
        ]);
    }

    /**
     * Get all tasks (admin)
     */
    public function index(): JsonResponse
    {
        $tasks = Task::all();

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $tasks,
                'total_tasks' => $tasks->count(),
            ]
        ]);
    }

    /**
     * Create a new task (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:task_categories,id',
            'task_type' => 'required|string|in:like,follow,subscribe,comment',
            'platform' => 'required|string|max:100',
            'instructions' => 'nullable|string',
            'target_url' => 'required|url',
            'requirements' => 'nullable|array',
            'reward' => 'required|numeric|min:0.01',
            'estimated_duration_minutes' => 'required|integer|min:1',
            'requires_photo' => 'boolean',
            'is_active' => 'boolean',
            'task_status' => 'nullable|string|in:active,pause,completed,suspended',
            'sort_order' => 'nullable|integer',
            'threshold_value' => 'nullable|integer|min:0',
            'task_completion_count' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:255'
        ]);

        $task = Task::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => $task
        ], 201);
    }

    /**
     * Get specific task (admin)
     */
    public function show(int $id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
                'error' => 'TaskNotFound'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task
        ]);
    }

    /**
     * Update task (admin)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
                'error' => 'TaskNotFound'
            ], 404);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:task_categories,id',
            'task_type' => 'sometimes|string|max:100',
            'platform' => 'sometimes|string|max:100',
            'instructions' => 'nullable|string',
            'target_url' => 'sometimes|url',
            'requirements' => 'nullable|array',
            'reward' => 'sometimes|numeric|min:0.01',
            'estimated_duration_minutes' => 'sometimes|integer|min:1',
            'requires_photo' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'task_status' => 'sometimes|string|in:active,pause,completed,suspended',
            'sort_order' => 'nullable|integer',
            'threshold_value' => 'nullable|integer|min:0',
            'task_completion_count' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:255'
        ]);

        $task->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => $task
        ]);
    }

    /**
     * Delete task (admin)
     */
    public function destroy(int $id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
                'error' => 'TaskNotFound'
            ], 404);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully'
        ]);
    }

    /**
     * Start the self-scheduling task system
     */
    public function startScheduler(Request $request): JsonResponse
    {
        $immediate = $request->boolean('immediate', false);
        
        try {
            if ($immediate) {
                // Start immediately
                \App\Jobs\AssignDailyTasksJob::dispatch();
                $message = 'Task assignment started immediately';
            } else {
                // Calculate next run time (next 6:00 AM)
                $now = now();
                $nextRun = $now->copy()->setTime(6, 0, 0);
                
                if ($now->greaterThan($nextRun)) {
                    $nextRun->addDay();
                }
                
                \App\Jobs\AssignDailyTasksJob::dispatch()->delay($nextRun);
                $message = "Self-scheduling task system started. Next run: {$nextRun->format('Y-m-d H:i:s')}";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'immediate' => $immediate,
                    'next_run' => $immediate ? 'immediate' : $nextRun->format('Y-m-d H:i:s'),
                    'scheduler_type' => 'self-scheduling'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start scheduler',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
