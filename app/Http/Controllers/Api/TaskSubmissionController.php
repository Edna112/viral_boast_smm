<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskSubmission;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Account;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaskSubmissionController extends Controller
{

    /**
     * Submit proof image for a completed task
     */
    public function submitProof(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'task_assignment_id' => ['nullable', 'integer', 'exists:task_assignments,id'],
            'image_url' => ['required', 'string', 'url'], // Image URL uploaded from frontend
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
            // Create submission record with image URL from frontend
            $submission = TaskSubmission::create([
                'user_uuid' => $user->uuid,
                'task_id' => $request->task_id,
                'task_assignment_id' => $request->task_assignment_id,
                'image_url' => $request->image_url,
                'public_id' => null, // No longer using Cloudinary public_id
                'description' => $request->description,
                'status' => 'pending',
            ]);

            // Increment task completion count
            $task->increment('task_completion_count');

            // Increment user's daily completion count
            $user->increment('tasks_completed_today');

            // Move task from assigned_tasks to completed_tasks in user's profile
            $user->moveTaskToCompleted($request->task_id);

            // Process automatic payment based on user's membership
            $paymentAmount = $this->processTaskPayment($user);

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
                        'benefit' => $task->benefit,
                        'task_completion_count' => $task->fresh()->task_completion_count, // Get updated count
                    ],
                    'user_stats' => [
                        'tasks_completed_today' => $user->fresh()->tasks_completed_today, // Get updated count
                        'total_points' => $user->total_points,
                    ],
                    'payment' => [
                        'amount_earned' => $paymentAmount,
                        'currency' => 'USD',
                        'payment_type' => 'task_completion'
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Task submission error: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'user_uuid' => $user->uuid ?? null,
                'task_id' => $request->task_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit task proof',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'user_uuid' => $user->uuid ?? null,
                    'task_id' => $request->task_id ?? null
                ]
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

    /**
     * Process automatic payment for task completion based on user's membership
     */
    private function processTaskPayment($user): float
    {
        try {
            // Load user's membership
            $user->load('membership');
            
            if (!$user->membership) {
                \Log::warning("No membership found for user {$user->uuid} during task payment");
                return 0.00;
            }

            // Get the benefit amount per task from membership
            $benefitAmount = $user->membership->benefit_amount_per_task;
            
            if (!$benefitAmount || $benefitAmount <= 0) {
                \Log::warning("Invalid or zero benefit_amount_per_task for user {$user->uuid} membership {$user->membership->id}");
                return 0.00;
            }

            // Get or create user's account
            $account = Account::getOrCreateForUser($user->uuid);
            
            // Add funds to account with 'task' type
            if ($account->addFunds($benefitAmount, 'task')) {
                \Log::info("Task payment processed: {$benefitAmount} added to account for user {$user->uuid}");
                return $benefitAmount;
            } else {
                \Log::error("Failed to add task payment to account for user {$user->uuid}");
                return 0.00;
            }

        } catch (\Exception $e) {
            \Log::error("Error processing task payment for user {$user->uuid}: " . $e->getMessage());
            return 0.00;
        }
    }
}
