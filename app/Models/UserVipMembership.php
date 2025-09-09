<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class UserVipMembership extends Model
{
    protected $fillable = [
        'user_id',
        'vip_membership_id',
        'started_at',
        'expires_at',
        'is_active',
        'daily_tasks_completed',
        'last_reset_date',
        'total_points_earned'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'last_reset_date' => 'date',
        'total_points_earned' => 'decimal:2'
    ];

    /**
     * Get the user that owns this membership
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the VIP membership details
     */
    public function vipMembership(): BelongsTo
    {
        return $this->belongsTo(VipMembership::class);
    }

    /**
     * Get task assignments for this membership
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Check if membership is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if membership is active and not expired
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Get remaining days for this membership
     */
    public function getRemainingDays(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        
        return Carbon::now()->diffInDays($this->expires_at, false);
    }

    /**
     * Check if daily tasks need to be reset
     */
    public function needsDailyReset(): bool
    {
        if (!$this->last_reset_date) {
            return true;
        }
        
        return $this->last_reset_date->isPast();
    }

    /**
     * Reset daily task counter
     */
    public function resetDailyTasks(): void
    {
        $this->update([
            'daily_tasks_completed' => 0,
            'last_reset_date' => Carbon::today()
        ]);
    }

    /**
     * Increment daily tasks completed
     */
    public function incrementDailyTasks(): void
    {
        $this->increment('daily_tasks_completed');
    }

    /**
     * Check if user can receive more tasks today
     */
    public function canReceiveMoreTasks(): bool
    {
        return $this->daily_tasks_completed < $this->vipMembership->daily_task_limit;
    }

    /**
     * Get remaining tasks for today
     */
    public function getRemainingTasksToday(): int
    {
        return max(0, $this->vipMembership->daily_task_limit - $this->daily_tasks_completed);
    }

    /**
     * Scope for active memberships
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope for expired memberships
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }
}
