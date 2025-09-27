<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskSubmission;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaskSubmissionController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Submit proof image for a completed task
     */
    public function submitProof(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'task_assignment_id' => ['nullable', 'integer', 'exists:task_assignments,id'],
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'], // 5MB max
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if task exists and is active
        $task = Task::findOrFail($request->task_id);
        if (!$task->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Task is not active',
                'error' => 'TaskInactive'
            ], 400);
        }

        // Check if user has already submitted for this task
        $existingSubmission = TaskSubmission::where('user_uuid', $user->uuid)
            ->where('task_id', $request->task_id)
            ->first();

        if ($existingSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted proof for this task',
                'error' => 'DuplicateSubmission',
                'data' => [
                    'existing_submission' => [
                        'id' => $existingSubmission->id,
                        'status' => $existingSubmission->status,
                        'submitted_at' => $existingSubmission->created_at,
                        'image_url' => $existingSubmission->image_url,
                    ]
                ]
            ], 409);
        }

        try {
            // Upload image to Cloudinary
            $uploadResult = $this->cloudinaryService->uploadTaskSubmissionImage(
                $request->file('image'),
                $user->uuid,
                $request->task_id
            );

            if (!$uploadResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload image',
                    'error' => $uploadResult['error']
                ], 422);
            }

            // Create submission record
            $submission = TaskSubmission::create([
                'user_uuid' => $user->uuid,
                'task_id' => $request->task_id,
                'task_assignment_id' => $request->task_assignment_id,
                'image_url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id'],
                'description' => $request->description,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task proof submitted successfully',
                'data' => [
                    'submission' => [
                        'id' => $submission->id,
                        'uuid' => $submission->uuid,
                        'task_id' => $submission->task_id,
                        'image_url' => $submission->image_url,
                        'description' => $submission->description,
                        'status' => $submission->status,
                        'submitted_at' => $submission->created_at,
                        'time_since_submission' => $submission->time_since_submission,
                    ],
                    'task' => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'points' => $task->points,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit task proof',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's task submissions
     */
    public function getUserSubmissions(Request $request)
    {
        $user = $request->user();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $status = $request->get('status'); // pending, approved, rejected

        $query = TaskSubmission::with(['task', 'taskAssignment', 'reviewer'])
            ->where('user_uuid', $user->uuid)
            ->orderBy('created_at', 'desc');

        if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        $submissions = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'submissions' => $submissions->items(),
                'pagination' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                    'per_page' => $submissions->perPage(),
                    'total' => $submissions->total(),
                    'has_more' => $submissions->hasMorePages(),
                ],
                'summary' => [
                    'total_submissions' => TaskSubmission::where('user_uuid', $user->uuid)->count(),
                    'pending_submissions' => TaskSubmission::where('user_uuid', $user->uuid)->pending()->count(),
                    'approved_submissions' => TaskSubmission::where('user_uuid', $user->uuid)->approved()->count(),
                    'rejected_submissions' => TaskSubmission::where('user_uuid', $user->uuid)->rejected()->count(),
                ]
            ]
        ]);
    }

    /**
     * Get specific submission details
     */
    public function getSubmission(Request $request, $id)
    {
        $user = $request->user();

        $submission = TaskSubmission::with(['task', 'taskAssignment', 'reviewer'])
            ->where('id', $id)
            ->where('user_uuid', $user->uuid)
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found',
                'error' => 'SubmissionNotFound'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => [
                    'id' => $submission->id,
                    'uuid' => $submission->uuid,
                    'task_id' => $submission->task_id,
                    'image_url' => $submission->image_url,
                    'description' => $submission->description,
                    'status' => $submission->status,
                    'admin_notes' => $submission->admin_notes,
                    'submitted_at' => $submission->created_at,
                    'reviewed_at' => $submission->reviewed_at,
                    'time_since_submission' => $submission->time_since_submission,
                    'time_since_review' => $submission->time_since_review,
                    'formatted_submission_date' => $submission->formatted_submission_date,
                    'formatted_review_date' => $submission->formatted_review_date,
                ],
                'task' => [
                    'id' => $submission->task->id,
                    'title' => $submission->task->title,
                    'description' => $submission->task->description,
                    'points' => $submission->task->points,
                    'category' => $submission->task->category ?? 'General',
                ],
                'reviewer' => $submission->reviewer ? [
                    'name' => $submission->reviewer->name,
                    'email' => $submission->reviewer->email,
                ] : null,
            ]
        ]);
    }

    /**
     * Get submission statistics for user
     */
    public function getSubmissionStats(Request $request)
    {
        $user = $request->user();

        // Get comprehensive task completion statistics
        $taskStats = TaskSubmission::getUserTaskStats($user->uuid);

        $stats = [
            'total_submissions' => $taskStats['total_submissions'],
            'pending_submissions' => $taskStats['pending_submissions'],
            'approved_submissions' => $taskStats['approved_submissions'],
            'rejected_submissions' => TaskSubmission::where('user_uuid', $user->uuid)->rejected()->count(),
            'approval_rate' => $taskStats['approval_rate'],
            'total_tasks' => $taskStats['total_tasks'],
            'tasks_completed_today' => $taskStats['tasks_completed_today'],
            'last_task_reset_date' => $taskStats['last_task_reset_date'],
            'daily_task_limit' => $taskStats['daily_task_limit'],
            'can_complete_more_tasks' => $taskStats['can_complete_more_tasks'],
            'membership_level' => $taskStats['membership_level'],
            'recent_submissions' => TaskSubmission::where('user_uuid', $user->uuid)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'task_id', 'status', 'created_at'])
                ->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'task_id' => $submission->task_id,
                        'status' => $submission->status,
                        'submitted_at' => $submission->created_at,
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
