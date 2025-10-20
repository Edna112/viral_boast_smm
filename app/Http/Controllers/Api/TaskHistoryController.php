<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskHistoryController extends Controller
{
    /**
     * Get user's task submission history
     */
    public function getUserHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            $status = $request->input('status'); // optional filter
            $dateFrom = $request->input('date_from'); // optional filter
            $dateTo = $request->input('date_to'); // optional filter

            $query = TaskHistory::byUser($user->uuid)
                ->with(['task', 'reviewer'])
                ->orderBy('submission_date', 'desc')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($dateFrom) {
                $query->where('submission_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->where('submission_date', '<=', $dateTo);
            }

            $history = $query->paginate($perPage, ['*'], 'page', $page);

            $historyData = $history->map(function ($item) {
                return [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'task' => [
                        'id' => $item->task->id,
                        'title' => $item->task->title,
                        'description' => $item->task->description,
                        'category' => $item->task->category,
                        'platform' => $item->task->platform,
                        'benefit' => $item->task->benefit,
                    ],
                    'image_url' => $item->image_url,
                    'description' => $item->description,
                    'status' => $item->status,
                    'admin_notes' => $item->admin_notes,
                    'reviewer' => $item->reviewer ? [
                        'name' => $item->reviewer->name,
                        'email' => $item->reviewer->email,
                    ] : null,
                    'submission_date' => $item->submission_date,
                    'archived_date' => $item->archived_date,
                    'reviewed_at' => $item->reviewed_at,
                    'formatted_submission_date' => $item->formatted_submission_date,
                    'formatted_review_date' => $item->formatted_review_date,
                    'time_since_submission' => $item->time_since_submission,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Task history retrieved successfully',
                'data' => [
                    'history' => $historyData,
                    'pagination' => [
                        'current_page' => $history->currentPage(),
                        'last_page' => $history->lastPage(),
                        'per_page' => $history->perPage(),
                        'total' => $history->total(),
                        'has_more' => $history->hasMorePages(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's task history statistics
     */
    public function getUserStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $stats = TaskHistory::getUserHistoryStats($user->uuid);

            return response()->json([
                'success' => true,
                'message' => 'Task history statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task history statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific task history entry
     */
    public function getHistoryEntry(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $historyEntry = TaskHistory::byUser($user->uuid)
                ->with(['task', 'reviewer'])
                ->find($id);

            if (!$historyEntry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task history entry not found',
                ], 404);
            }

            $historyData = [
                'id' => $historyEntry->id,
                'uuid' => $historyEntry->uuid,
                'task' => [
                    'id' => $historyEntry->task->id,
                    'title' => $historyEntry->task->title,
                    'description' => $historyEntry->task->description,
                    'category' => $historyEntry->task->category,
                    'platform' => $historyEntry->task->platform,
                    'benefit' => $historyEntry->task->benefit,
                    'instructions' => $historyEntry->task->instructions,
                ],
                'image_url' => $historyEntry->image_url,
                'description' => $historyEntry->description,
                'status' => $historyEntry->status,
                'admin_notes' => $historyEntry->admin_notes,
                'reviewer' => $historyEntry->reviewer ? [
                    'name' => $historyEntry->reviewer->name,
                    'email' => $historyEntry->reviewer->email,
                ] : null,
                'submission_date' => $historyEntry->submission_date,
                'archived_date' => $historyEntry->archived_date,
                'reviewed_at' => $historyEntry->reviewed_at,
                'formatted_submission_date' => $historyEntry->formatted_submission_date,
                'formatted_review_date' => $historyEntry->formatted_review_date,
                'time_since_submission' => $historyEntry->time_since_submission,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Task history entry retrieved successfully',
                'data' => $historyData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task history entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all task history (Admin only)
     */
    public function getAllHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || !$user->is_admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }

            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 50);
            $status = $request->input('status');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $userUuid = $request->input('user_uuid');

            $query = TaskHistory::with(['user', 'task', 'reviewer'])
                ->orderBy('submission_date', 'desc')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($dateFrom) {
                $query->where('submission_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->where('submission_date', '<=', $dateTo);
            }

            if ($userUuid) {
                $query->where('user_uuid', $userUuid);
            }

            $history = $query->paginate($perPage, ['*'], 'page', $page);

            $historyData = $history->map(function ($item) {
                return [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'user' => [
                        'uuid' => $item->user->uuid,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                    ],
                    'task' => [
                        'id' => $item->task->id,
                        'title' => $item->task->title,
                        'category' => $item->task->category,
                        'platform' => $item->task->platform,
                        'benefit' => $item->task->benefit,
                    ],
                    'image_url' => $item->image_url,
                    'description' => $item->description,
                    'status' => $item->status,
                    'admin_notes' => $item->admin_notes,
                    'reviewer' => $item->reviewer ? [
                        'name' => $item->reviewer->name,
                        'email' => $item->reviewer->email,
                    ] : null,
                    'submission_date' => $item->submission_date,
                    'archived_date' => $item->archived_date,
                    'reviewed_at' => $item->reviewed_at,
                    'formatted_submission_date' => $item->formatted_submission_date,
                    'formatted_review_date' => $item->formatted_review_date,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'All task history retrieved successfully',
                'data' => [
                    'history' => $historyData,
                    'pagination' => [
                        'current_page' => $history->currentPage(),
                        'last_page' => $history->lastPage(),
                        'per_page' => $history->perPage(),
                        'total' => $history->total(),
                        'has_more' => $history->hasMorePages(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve all task history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
