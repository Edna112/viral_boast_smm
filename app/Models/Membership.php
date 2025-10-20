<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'membership';
    protected $fillable = [
        'membership_name',
        'membership_icon',
        'description',
        'tasks_per_day',
        'max_tasks',
        'price',
        'benefit_amount_per_task',
        'is_active',
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
                    ->where('users.isActive', true) // User account is active
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
        return 3; // Default value since this column was removed
    }

    /**
     * Get daily task limit for this membership
     */
    public function getDailyTaskLimit(): int
    {
        return $this->tasks_per_day ?? 5;
    }

    /**
     * Get distribution priority for this membership
     */
    public function getDistributionPriority(): int
    {
        return 1; // Default value since this column was removed
    }
}
