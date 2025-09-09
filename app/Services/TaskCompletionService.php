<?php

namespace App\Services;

use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TaskCompletionService
{
    /**
     * Complete a task assignment with photo upload
     */
    public function completeTask(int $assignmentId, ?UploadedFile $photo = null): array
    {
        $assignment = TaskAssignment::with(['user', 'task'])->find($assignmentId);

        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Task assignment not found',
                'error' => 'AssignmentNotFound'
            ];
        }

        if (!$assignment->canBeCompleted()) {
            return [
                'success' => false,
                'message' => 'Task cannot be completed (expired or already completed)',
                'error' => 'TaskNotCompletable'
            ];
        }

        try {
            $photoUrl = null;
            
            // Handle photo upload if required
            if ($assignment->task->requires_photo) {
                if (!$photo) {
                    return [
                        'success' => false,
                        'message' => 'Photo is required for this task',
                        'error' => 'PhotoRequired'
                    ];
                }

                $photoUrl = $this->uploadPhoto($photo, $assignment);
                if (!$photoUrl) {
                    return [
                        'success' => false,
                        'message' => 'Failed to upload photo',
                        'error' => 'PhotoUploadFailed'
                    ];
                }
            }

            // Mark task as completed
            $success = $assignment->markAsCompleted($photoUrl);

            if (!$success) {
                return [
                    'success' => false,
                    'message' => 'Failed to complete task',
                    'error' => 'CompletionFailed'
                ];
            }

            return [
                'success' => true,
                'message' => 'Task completed successfully',
                'data' => [
                    'assignment_id' => $assignment->id,
                    'task_title' => $assignment->task->title,
                    'points_earned' => $assignment->final_reward,
                    'vip_multiplier' => $assignment->vip_multiplier,
                    'base_points' => $assignment->base_points,
                    'completion_photo_url' => $photoUrl,
                    'completed_at' => $assignment->completed_at,
                    'user_total_points' => $assignment->user->total_points,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Task completion failed', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while completing the task',
                'error' => 'CompletionError'
            ];
        }
    }

    /**
     * Upload photo for task completion
     */
    private function uploadPhoto(UploadedFile $photo, TaskAssignment $assignment): ?string
    {
        try {
            // Validate photo
            if (!$this->validatePhoto($photo)) {
                return null;
            }

            // Generate unique filename
            $filename = 'task_completion_' . $assignment->id . '_' . time() . '.' . $photo->getClientOriginalExtension();
            
            // Store photo
            $path = $photo->storeAs('task_completions', $filename, 'public');

            return Storage::url($path);

        } catch (\Exception $e) {
            Log::error('Photo upload failed', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate uploaded photo
     */
    private function validatePhoto(UploadedFile $photo): bool
    {
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($photo->getMimeType(), $allowedTypes)) {
            return false;
        }

        // Check file size (max 5MB)
        if ($photo->getSize() > 5 * 1024 * 1024) {
            return false;
        }

        return true;
    }

    /**
     * Get user's task completion history
     */
    public function getUserCompletionHistory(User $user, int $limit = 50): array
    {
        $assignments = TaskAssignment::with('task')
                                   ->where('user_id', $user->id)
                                   ->where('status', 'completed')
                                   ->orderBy('completed_at', 'desc')
                                   ->limit($limit)
                                   ->get();

        return $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'task_title' => $assignment->task->title,
                'completed_at' => $assignment->completed_at,
                'points_earned' => $assignment->final_reward,
                'vip_multiplier' => $assignment->vip_multiplier,
                'completion_photo_url' => $assignment->completion_photo_url,
            ];
        })->toArray();
    }

    /**
     * Get completion statistics for a user
     */
    public function getUserStats(User $user): array
    {
        $today = today();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        return [
            'today' => [
                'tasks_completed' => $user->tasks_completed_today,
                'points_earned' => TaskAssignment::where('user_id', $user->id)
                                               ->where('status', 'completed')
                                               ->whereDate('completed_at', $today)
                                               ->sum('final_reward'),
            ],
            'this_week' => [
                'tasks_completed' => TaskAssignment::where('user_id', $user->id)
                                                 ->where('status', 'completed')
                                                 ->where('completed_at', '>=', $thisWeek)
                                                 ->count(),
                'points_earned' => TaskAssignment::where('user_id', $user->id)
                                               ->where('status', 'completed')
                                               ->where('completed_at', '>=', $thisWeek)
                                               ->sum('final_reward'),
            ],
            'this_month' => [
                'tasks_completed' => TaskAssignment::where('user_id', $user->id)
                                                 ->where('status', 'completed')
                                                 ->where('completed_at', '>=', $thisMonth)
                                                 ->count(),
                'points_earned' => TaskAssignment::where('user_id', $user->id)
                                               ->where('status', 'completed')
                                               ->where('completed_at', '>=', $thisMonth)
                                               ->sum('final_reward'),
            ],
            'all_time' => [
                'total_points' => $user->total_points,
                'tasks_completed' => TaskAssignment::where('user_id', $user->id)
                                                 ->where('status', 'completed')
                                                 ->count(),
            ]
        ];
    }
}
