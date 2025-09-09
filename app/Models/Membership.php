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
        'userid',
        'benefits',
        'price',
        'duration_days',
        'reward_multiplier',
        'priority_level',
        'is_active',
    ];

   public function user()
    {
        return $this->belongsTo(User::class, 'userid', 'id');
    }

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
}
