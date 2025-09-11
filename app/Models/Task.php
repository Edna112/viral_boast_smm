<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $table = 'task';
    
    protected $fillable = [
        'task_name',
        'task_type',
        'task_url',
        'user_id',
        'membership_id',
        'status',
        'duration'
    ];

    protected $casts = [
        'duration' => 'datetime'
    ];

    /**
     * Get the category this task belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    /**
     * Get all assignments for this task
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get active assignments for this task
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->where('status', 'pending');
    }

    /**
     * Get completed assignments for this task
     */
    public function completedAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->where('status', 'completed');
    }

    /**
     * Scope for active tasks
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope by platform
     */
    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Get tasks that require photo submission
     */
    public function scopeRequiresPhoto($query)
    {
        return $query->where('requires_photo', true);
    }

    /**
     * Calculate reward for a specific VIP level
     */
    public function calculateReward($vipMultiplier): int
    {
        return (int) round($this->base_points * $vipMultiplier);
    }

    /**
     * Get task statistics
     */
    public function getStats()
    {
        return [
            'total_assignments' => $this->assignments()->count(),
            'completed_assignments' => $this->completedAssignments()->count(),
            'pending_assignments' => $this->activeAssignments()->count(),
            'completion_rate' => $this->assignments()->count() > 0 
                ? round(($this->completedAssignments()->count() / $this->assignments()->count()) * 100, 2)
                : 0
        ];
    }
}