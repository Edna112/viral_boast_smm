<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category_id',
        'task_type',
        'platform',
        'instructions',
        'target_url',
        'requirements',
        'reward',
        'estimated_duration_minutes',
        'requires_photo',
        'is_active',
        'task_status',
        'sort_order',
        'threshold_value',
        'task_completion_count',
        'category'
    ];

    protected $casts = [
        'requirements' => 'array',
        'requires_photo' => 'boolean',
        'is_active' => 'boolean',
        'reward' => 'decimal:2',
        'task_completion_count' => 'integer'
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
        return $query->where('is_active', true);
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
     * Scope by task status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('task_status', $status);
    }

    /**
     * Calculate reward for a specific VIP level
     */
    public function calculateReward($vipMultiplier): float
    {
        return round($this->reward * $vipMultiplier, 2);
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