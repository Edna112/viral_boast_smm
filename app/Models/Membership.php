<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'membership';
    protected $fillable = [
        'membership_name',
        'description',
        'tasks_per_day',
        'max_tasks',
        'task_link',
        'benefits',
        'price',
        'reward_multiplier',
        'priority_level',
        'is_active',
        'daily_task_limit',
        'max_tasks_per_distribution',
        'distribution_priority',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_memberships')
                    ->withPivot(['started_at', 'expires_at', 'is_active', 'daily_tasks_completed', 'last_reset_date'])
                    ->withTimestamps();
    }

    public function activeUsers()
    {
        return $this->users()
                    ->wherePivot('is_active', true)
                    ->wherePivot('expires_at', '>', now());
    }

    /**
     * Get users eligible for task distribution
     */
    public function getEligibleUsersForDistribution()
    {
        return $this->activeUsers()
                    ->where('users.is_active', true) // User account is active
                    ->where('users.updated_at', '>=', now()->subDays(7)) // Active within last 7 days
                    ->whereHas('memberships', function($query) {
                        $query->where('membership_id', $this->id)
                              ->where('user_memberships.is_active', true)
                              ->where(function($q) {
                                  $q->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                              });
                    })
                    ->orderBy('users.updated_at', 'desc')
                    ->get();
    }

    /**
     * Get task distribution count for this membership
     */
    public function getMaxTasksPerDistribution(): int
    {
        return $this->max_tasks_per_distribution ?? 3;
    }

    /**
     * Get daily task limit for this membership
     */
    public function getDailyTaskLimit(): int
    {
        return $this->daily_task_limit ?? 5;
    }

    /**
     * Get distribution priority for this membership
     */
    public function getDistributionPriority(): int
    {
        return $this->distribution_priority ?? 1;
    }
}
