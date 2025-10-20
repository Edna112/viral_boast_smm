<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyTaskAssignment extends Model
{
    protected $fillable = [
        'user_uuid',
        'assignment_date',
        'tasks_assigned_count',
        'assigned_task_ids',
        'membership_id',
        'membership_tasks_per_day',
    ];

    protected $casts = [
        'assignment_date' => 'date',
        'assigned_task_ids' => 'array',
    ];

    /**
     * Get the user this assignment belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Check if user has tasks assigned for today
     */
    public static function hasTasksForToday(string $userUuid): bool
    {
        return self::where('user_uuid', $userUuid)
            ->where('assignment_date', today())
            ->exists();
    }

    /**
     * Get today's assignment for user
     */
    public static function getTodayAssignment(string $userUuid): ?self
    {
        return self::where('user_uuid', $userUuid)
            ->where('assignment_date', today())
            ->first();
    }

    /**
     * Create or update today's assignment
     */
    public static function createOrUpdateToday(string $userUuid, array $taskIds, $membershipId = null, $tasksPerDay = null): self
    {
        return self::updateOrCreate(
            [
                'user_uuid' => $userUuid,
                'assignment_date' => today(),
            ],
            [
                'tasks_assigned_count' => count($taskIds),
                'assigned_task_ids' => $taskIds,
                'membership_id' => $membershipId,
                'membership_tasks_per_day' => $tasksPerDay,
            ]
        );
    }

    /**
     * Check if membership has changed since last assignment
     */
    public static function hasMembershipChanged(string $userUuid, $currentMembershipId, $currentTasksPerDay): bool
    {
        $todayAssignment = self::getTodayAssignment($userUuid);
        
        if (!$todayAssignment) {
            return true; // No assignment exists, so it's a change
        }
        
        return $todayAssignment->membership_id !== $currentMembershipId || 
               $todayAssignment->membership_tasks_per_day !== $currentTasksPerDay;
    }
}
