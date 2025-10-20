<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserMembership extends Model
{
    protected $fillable = [
        'user_uuid',
        'membership_id',
        'started_at',
        'expires_at',
        'is_active',
        'daily_tasks_completed',
        'last_reset_date',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'last_reset_date' => 'date',
    ];

    /**
     * Get the user this membership belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the membership details
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * Check if membership is active
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    /**
     * Check if membership is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get remaining days
     */
    public function getRemainingDays(): int
    {
        return max(0, $this->expires_at->diffInDays(now()));
    }

    /**
     * Reset daily task counter
     */
    public function resetDailyTasks(): void
    {
        $this->update([
            'daily_tasks_completed' => 0,
            'last_reset_date' => today(),
        ]);
    }

    /**
     * Check if daily tasks need reset
     */
    public function needsDailyReset(): bool
    {
        return $this->last_reset_date !== today();
    }

    /**
     * Get membership details for API response
     */
    public function getDetails()
    {
        return [
            'id' => $this->id,
            'membership' => [
                'id' => $this->membership->id,
                'membership_name' => $this->membership->membership_name,
                'membership_icon' => $this->membership->membership_icon,
                'description' => $this->membership->description,
                'tasks_per_day' => $this->membership->tasks_per_day,
                'max_tasks' => $this->membership->max_tasks,
                'price' => $this->membership->price,
                'benefit_amount_per_task' => $this->membership->benefit_amount_per_task,
                'is_active' => $this->membership->is_active,
                'created_at' => $this->membership->created_at,
                'updated_at' => $this->membership->updated_at,
            ],
            'subscription' => [
                'started_at' => $this->started_at,
                'expires_at' => $this->expires_at,
                'is_active' => $this->isActive(),
                'remaining_days' => $this->getRemainingDays(),
            ],
            'daily_progress' => [
                'tasks_completed' => $this->daily_tasks_completed,
                'daily_limit' => $this->membership->tasks_per_day,
                'remaining_tasks' => max(0, $this->membership->tasks_per_day - $this->daily_tasks_completed),
                'last_reset_date' => $this->last_reset_date,
            ]
        ];
    }
}
