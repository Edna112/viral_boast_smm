<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TaskSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_uuid',
        'task_id',
        'task_assignment_id',
        'image_url',
        'public_id',
        'description',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $primaryKey = 'id';
    public $incrementing = true;

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = \Illuminate\Support\Str::uuid();
            }
        });

        static::created(function ($submission) {
            // Update user's daily task completion count
            $submission->updateUserTaskCompletion();
            
            // Update user's total tasks count
            $submission->updateUserTotalTasks();
            
            // Update task's completion count
            $submission->updateTaskCompletionCount();
        });
    }

    /**
     * Get the user who submitted this task
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the task this submission is for
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the task assignment this submission is for
     */
    public function taskAssignment(): BelongsTo
    {
        return $this->belongsTo(TaskAssignment::class);
    }

    /**
     * Get the admin who reviewed this submission
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'uuid');
    }

    /**
     * Scope for pending submissions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved submissions
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected submissions
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for submissions by user
     */
    public function scopeByUser($query, $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * Scope for submissions for a specific task
     */
    public function scopeForTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Check if submission is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if submission is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if submission is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get the time since submission
     */
    public function getTimeSinceSubmissionAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the time since review
     */
    public function getTimeSinceReviewAttribute(): ?string
    {
        return $this->reviewed_at ? $this->reviewed_at->diffForHumans() : null;
    }

    /**
     * Get formatted submission date
     */
    public function getFormattedSubmissionDateAttribute(): string
    {
        return $this->created_at->format('M j, Y \a\t g:i A');
    }

    /**
     * Get formatted review date
     */
    public function getFormattedReviewDateAttribute(): ?string
    {
        return $this->reviewed_at ? $this->reviewed_at->format('M j, Y \a\t g:i A') : null;
    }

    /**
     * Update user's daily task completion count
     */
    public function updateUserTaskCompletion(): void
    {
        if (!$this->user_uuid) {
            return;
        }

        $user = User::where('uuid', $this->user_uuid)->first();
        if (!$user) {
            return;
        }

        $today = now()->toDateString();
        $lastResetDate = $user->last_task_reset_date;

        // Reset daily count if it's a new day
        if (!$lastResetDate || $lastResetDate !== $today) {
            $user->update([
                'tasks_completed_today' => 1,
                'last_task_reset_date' => $today,
            ]);
        } else {
            // Increment today's count
            $user->increment('tasks_completed_today');
        }
    }

    /**
     * Update user's total tasks count
     */
    public function updateUserTotalTasks(): void
    {
        if (!$this->user_uuid) {
            return;
        }

        $user = User::where('uuid', $this->user_uuid)->first();
        if (!$user) {
            return;
        }

        // Increment user's total tasks count
        $user->incrementTotalTasks();
    }

    /**
     * Update task's completion count
     */
    public function updateTaskCompletionCount(): void
    {
        if (!$this->task_id) {
            return;
        }

        $task = Task::find($this->task_id);
        if (!$task) {
            return;
        }

        // Increment task completion count
        $task->increment('task_completion_count');
    }

    /**
     * Get task completion statistics for user
     */
    public static function getUserTaskStats(string $userUuid): array
    {
        $user = User::where('uuid', $userUuid)->first();
        if (!$user) {
            return [];
        }

        $totalSubmissions = self::where('user_uuid', $userUuid)->count();
        $approvedSubmissions = self::where('user_uuid', $userUuid)->approved()->count();
        $pendingSubmissions = self::where('user_uuid', $userUuid)->pending()->count();

        return [
            'total_submissions' => $totalSubmissions,
            'approved_submissions' => $approvedSubmissions,
            'pending_submissions' => $pendingSubmissions,
            'total_tasks' => $user->total_tasks,
            'tasks_completed_today' => $user->tasks_completed_today,
            'last_task_reset_date' => $user->last_task_reset_date,
            'daily_task_limit' => $user->getDailyTaskLimit(),
            'can_complete_more_tasks' => $user->canCompleteMoreTasks(),
            'membership_level' => $user->getMembershipLevel(),
            'approval_rate' => $totalSubmissions > 0 ? round(($approvedSubmissions / $totalSubmissions) * 100, 2) : 0,
        ];
    }

    /**
     * Reset daily task completion count for all users (to be run daily)
     */
    public static function resetDailyTaskCounts(): int
    {
        $today = now()->toDateString();
        
        return User::where('last_task_reset_date', '!=', $today)
            ->orWhereNull('last_task_reset_date')
            ->update([
                'tasks_completed_today' => 0,
                'last_task_reset_date' => $today,
            ]);
    }
}