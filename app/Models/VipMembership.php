<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipMembership extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'reward_multiplier',
        'daily_task_limit',
        'max_tasks',
        'duration_days',
        'benefits',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'benefits' => 'array',
        'price' => 'decimal:2',
        'reward_multiplier' => 'decimal:1',
        'is_active' => 'boolean'
    ];

    /**
     * Get all user memberships for this VIP level
     */
    public function userMemberships(): HasMany
    {
        return $this->hasMany(UserVipMembership::class);
    }

    /**
     * Get active user memberships for this VIP level
     */
    public function activeUserMemberships(): HasMany
    {
        return $this->hasMany(UserVipMembership::class)->where('is_active', true);
    }

    /**
     * Get task assignments for this VIP level
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Scope for active VIP memberships
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('reward_multiplier');
    }

    /**
     * Get VIP level by slug
     */
    public static function getBySlug($slug)
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Get all VIP levels with their multipliers
     */
    public static function getVipLevels()
    {
        return static::active()->ordered()->get()->mapWithKeys(function ($membership) {
            return [$membership->slug => [
                'name' => $membership->name,
                'multiplier' => $membership->reward_multiplier,
                'daily_limit' => $membership->daily_task_limit,
                'max_tasks' => $membership->max_tasks
            ]];
        });
    }
}
