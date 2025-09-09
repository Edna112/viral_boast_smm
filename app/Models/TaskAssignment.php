<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TaskAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'task_id',
        'assigned_at',
        'expires_at',
        'status',
        'completion_photo_url',
        'completed_at',
        'base_points',
        'vip_multiplier',
        'final_reward',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'vip_multiplier' => 'decimal:1',
    ];

    /**
     * Get the user this assignment belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the task this assignment is for
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Scope for pending assignments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed assignments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for expired assignments
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope for assignments that expire today
     */
    public function scopeExpiresToday($query)
    {
        return $query->whereDate('expires_at', today());
    }

    /**
     * Scope for assignments assigned today
     */
    public function scopeAssignedToday($query)
    {
        return $query->whereDate('assigned_at', today());
    }

    /**
     * Check if assignment is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if assignment can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Mark assignment as completed
     */
    public function markAsCompleted(string $photoUrl = null): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->update([
            'status' => 'completed',
            'completion_photo_url' => $photoUrl,
            'completed_at' => now(),
        ]);

        // Award points to user
        $this->user->increment('total_points', $this->final_reward);
        $this->user->increment('tasks_completed_today');

        return true;
    }

    /**
     * Mark assignment as expired
     */
    public function markAsExpired(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update(['status' => 'expired']);
        return true;
    }

    /**
     * Get assignment details for API response
     */
    public function getDetails()
    {
        return [
            'id' => $this->id,
            'task' => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'description' => $this->task->description,
                'platform' => $this->task->platform,
                'target_url' => $this->task->target_url,
                'instructions' => $this->task->instructions,
                'requires_photo' => $this->task->requires_photo,
                'estimated_duration_minutes' => $this->task->estimated_duration_minutes,
            ],
            'assignment' => [
                'assigned_at' => $this->assigned_at,
                'expires_at' => $this->expires_at,
                'status' => $this->status,
                'time_remaining' => $this->expires_at->diffForHumans(),
                'is_expired' => $this->isExpired(),
            ],
            'rewards' => [
                'base_points' => $this->base_points,
                'vip_multiplier' => $this->vip_multiplier,
                'final_reward' => $this->final_reward,
            ],
            'completion' => [
                'completed_at' => $this->completed_at,
                'completion_photo_url' => $this->completion_photo_url,
            ]
        ];
    }
}