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
        'category',
        'task_type',
        'platform',
        'instructions',
        'target_url',
        'benefit',
        'is_active',
        'task_status',
        'priority',
        'threshold_value',
        'task_completion_count',
        'task_distribution_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'benefit' => 'decimal:2',
        'task_completion_count' => 'integer',
        'task_distribution_count' => 'integer',
        'threshold_value' => 'integer',
    ];

    /**
     * Get tasks by category (now using string category instead of foreign key)
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
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
     * Scope ordered by priority and creation date
     */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw("CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            END")->orderBy('created_at', 'desc');
    }

    /**
     * Scope by platform
     */
    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope by priority level
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope by task status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('task_status', $status);
    }

    /**
     * Calculate benefit for a specific VIP level
     */
    public function calculateBenefit($vipMultiplier): float
    {
        return round($this->benefit * $vipMultiplier, 2);
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
                : 0,
            'distribution_count' => $this->task_distribution_count,
            'completion_count' => $this->task_completion_count,
            'threshold_value' => $this->threshold_value,
            'benefit' => $this->benefit,
            'priority' => $this->priority,
            'task_status' => $this->task_status
        ];
    }

    /**
     * Check if task can be distributed
     */
    public function canBeDistributed(): bool
    {
        return $this->is_active 
            && $this->task_status === 'active'
            && $this->task_distribution_count < $this->threshold_value
            && $this->task_completion_count < $this->threshold_value;
    }

    /**
     * Check if task is at distribution limit
     */
    public function isAtDistributionLimit(): bool
    {
        return $this->task_distribution_count >= $this->threshold_value;
    }

    /**
     * Check if task is at completion limit
     */
    public function isAtCompletionLimit(): bool
    {
        return $this->task_completion_count >= $this->threshold_value;
    }

    /**
     * Increment distribution count
     */
    public function incrementDistributionCount(): void
    {
        $this->increment('task_distribution_count');
    }

    /**
     * Get available tasks for distribution
     */
    public static function getAvailableForDistribution($category = null)
    {
        $query = self::active()
            ->where('task_status', 'active')
            ->where(function($q) {
                $q->where('task_distribution_count', '<', \DB::raw('threshold_value'))
                  ->where('task_completion_count', '<', \DB::raw('threshold_value'));
            });

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderByRaw("CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            END")
            ->orderBy('created_at', 'asc')
            ->get();
    }
}