<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAssignment;
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
                'user_membership' => $user->activeMembership?->first(),
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
                            'base_points' => $task->base_points,
                            'estimated_duration_minutes' => $task->estimated_duration_minutes,
                            'requires_photo' => $task->requires_photo,
                            'instructions' => $task->instructions,
                            'target_url' => $task->target_url,
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
}
