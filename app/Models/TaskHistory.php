<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TaskHistory extends Model
{
    protected $table = 'task_history';

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
        'submission_date',
        'archived_date',
    ];

    protected $casts = [
        'submission_date' => 'date',
        'archived_date' => 'date',
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
            if (empty($model->archived_date)) {
                $model->archived_date = now()->toDateString();
            }
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
     * Scope for submissions on a specific date
     */
    public function scopeOnDate($query, $date)
    {
        return $query->where('submission_date', $date);
    }

    /**
     * Scope for submissions between dates
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('submission_date', [$startDate, $endDate]);
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
     * Get task history statistics for user
     */
    public static function getUserHistoryStats(string $userUuid): array
    {
        $user = User::where('uuid', $userUuid)->first();
        if (!$user) {
            return [];
        }

        $totalSubmissions = self::where('user_uuid', $userUuid)->count();
        $approvedSubmissions = self::where('user_uuid', $userUuid)->approved()->count();
        $rejectedSubmissions = self::where('user_uuid', $userUuid)->rejected()->count();

        // Get submissions by month for the last 6 months
        $monthlyStats = self::where('user_uuid', $userUuid)
            ->where('submission_date', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(submission_date, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();

        return [
            'total_submissions' => $totalSubmissions,
            'approved_submissions' => $approvedSubmissions,
            'rejected_submissions' => $rejectedSubmissions,
            'approval_rate' => $totalSubmissions > 0 ? round(($approvedSubmissions / $totalSubmissions) * 100, 2) : 0,
            'rejection_rate' => $totalSubmissions > 0 ? round(($rejectedSubmissions / $totalSubmissions) * 100, 2) : 0,
            'monthly_stats' => $monthlyStats,
            'last_submission_date' => self::where('user_uuid', $userUuid)->max('submission_date'),
        ];
    }

    /**
     * Archive task submissions from task_submissions table
     */
    public static function archiveSubmissions(): array
    {
        $results = [
            'archived_count' => 0,
            'errors' => []
        ];

        try {
            // Get all submissions from task_submissions table
            $submissions = TaskSubmission::all();

            foreach ($submissions as $submission) {
                try {
                    // Create history record
                    self::create([
                        'uuid' => $submission->uuid,
                        'user_uuid' => $submission->user_uuid,
                        'task_id' => $submission->task_id,
                        'task_assignment_id' => $submission->task_assignment_id,
                        'image_url' => $submission->image_url,
                        'public_id' => $submission->public_id,
                        'description' => $submission->description,
                        'status' => $submission->status,
                        'admin_notes' => $submission->admin_notes,
                        'reviewed_by' => $submission->reviewed_by,
                        'reviewed_at' => $submission->reviewed_at,
                        'submission_date' => $submission->created_at->toDateString(),
                        'archived_date' => now()->toDateString(),
                    ]);

                    // Delete from task_submissions table
                    $submission->delete();
                    $results['archived_count']++;

                } catch (\Exception $e) {
                    $results['errors'][] = "Failed to archive submission {$submission->id}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = 'Archive process failed: ' . $e->getMessage();
        }

        return $results;
    }
}
