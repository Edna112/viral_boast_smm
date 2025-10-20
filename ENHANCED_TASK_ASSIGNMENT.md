# Enhanced Task Assignment Algorithm

## Overview

The Enhanced Task Assignment Algorithm implements precise requirements for daily task distribution with comprehensive membership-based quotas, threshold management, and unique assignment constraints.

## Key Features

### 1. Daily Assignment Timing

-   Tasks are assigned daily starting from 00:00 AM onwards
-   Any task request made after midnight on a given day is considered a request for new tasks of that day
-   Tasks assigned to new users at any time during the day are based on current day's criteria

### 2. Membership-Based Quotas

-   Each user belongs to a membership level that determines daily task quota
-   Membership level determines `tasks_per_day` allocation
-   Users receive tasks based on their active membership status

### 3. Unique Assignment Constraints

-   Once a task has been assigned to a user, it must never be assigned again to the same user
-   Algorithm picks only tasks that have not previously been assigned to that user
-   Comprehensive tracking prevents duplicate assignments

### 4. Task Threshold Management

-   Each task has a threshold for maximum number of times it can be assigned across all users
-   Only tasks with `task_completion_count <= threshold_value` are available for assignment
-   Tasks with `task_distribution_count < threshold_value` can still be distributed

### 5. Counter Management

-   `task_distribution_count`: Incremented each time the task is assigned
-   `task_completion_count`: Incremented when a user completes a task
-   Both counters respect the task's threshold limits

### 6. Continuous Assignment

-   Even if a user hasn't completed previous tasks, the algorithm assigns new tasks based on their membership level quota
-   Users receive their full daily quota regardless of completion status

## Implementation Details

### Service Class: `EnhancedTaskAssignmentService`

#### Core Methods:

1. **`assignDailyTasks()`**

    - Assigns tasks to all active users
    - Uses database transactions for data integrity
    - Comprehensive error handling and logging

2. **`assignTasksToUser(User $user)`**

    - Assigns tasks to a specific user based on membership
    - Respects daily quotas and unique assignment constraints
    - Updates task distribution counters

3. **`assignTasksToNewUser(User $user)`**

    - Handles new user registrations during the day
    - Applies current day's criteria for assignment

4. **`getUserTaskStatus(User $user)`**

    - Returns user's current task assignment status
    - Shows membership details and remaining quota

5. **`resetDailyAssignments()`**

    - Marks expired assignments as expired
    - Run at midnight for daily cleanup

6. **`getDistributionStats()`**
    - Provides comprehensive distribution statistics
    - Shows efficiency metrics and task availability

### Task Model Enhancements

#### Updated `getAvailableForDistribution()` Method:

```php
public static function getAvailableForDistribution($category = null, $excludeTaskIds = [])
{
    $query = self::active()
        ->where('task_status', 'active')
        // Task-level threshold: only tasks with completion count <= threshold
        ->whereRaw('task_completion_count <= threshold_value')
        // Task-level threshold: only tasks with distribution count < threshold
        ->whereRaw('task_distribution_count < threshold_value');

    if ($category) {
        $query->where('category', $category);
    }

    if (!empty($excludeTaskIds)) {
        $query->whereNotIn('id', $excludeTaskIds);
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
```

## API Endpoints

### Admin Routes (Admin Authentication Required)

#### 1. Enhanced Daily Task Assignment

```
POST /api/v1/admin/tasks/assign-daily-enhanced
```

**Description:** Assigns tasks to all active users using enhanced algorithm
**Response:**

```json
{
    "success": true,
    "message": "Enhanced daily task assignment completed",
    "data": {
        "total_users": 150,
        "users_assigned": 120,
        "total_assignments": 360,
        "errors": []
    }
}
```

#### 2. Assign Tasks to Specific User

```
POST /api/v1/admin/tasks/assign-to-user-enhanced
```

**Description:** Assigns tasks to authenticated user using enhanced algorithm
**Response:**

```json
{
    "success": true,
    "message": "Tasks assigned successfully",
    "data": {
        "user_uuid": "user-uuid-here",
        "assigned_tasks": 3,
        "assignment_date": "2025-01-15"
    }
}
```

#### 3. Assign Tasks to New User

```
POST /api/v1/admin/tasks/assign-to-new-user
```

**Body:**

```json
{
    "user_uuid": "new-user-uuid"
}
```

**Description:** Assigns tasks to newly registered user

#### 4. Get User Task Status

```
GET /api/v1/admin/tasks/user-status
```

**Description:** Returns user's task assignment status
**Response:**

```json
{
    "success": true,
    "data": {
        "has_membership": true,
        "membership_name": "Premium",
        "tasks_per_day": 5,
        "assigned_today": 2,
        "remaining_today": 3,
        "can_receive_tasks": true
    }
}
```

#### 5. Reset Daily Assignments

```
POST /api/v1/admin/tasks/reset-daily-enhanced
```

**Description:** Resets daily assignments and marks expired tasks

#### 6. Get Enhanced Distribution Statistics

```
GET /api/v1/admin/tasks/distribution-stats-enhanced
```

**Description:** Returns comprehensive distribution statistics
**Response:**

```json
{
    "success": true,
    "data": {
        "tasks": {
            "total": 50,
            "active": 45,
            "available_for_distribution": 30
        },
        "assignments": {
            "total": 1200,
            "pending": 800,
            "completed": 400
        },
        "distribution_efficiency": 33.33
    }
}
```

### User Routes (User Authentication Required)

#### 1. Request Task Assignment

```
POST /api/v1/tasks/assign-enhanced
```

**Description:** User requests task assignment using enhanced algorithm

#### 2. Get Personal Task Status

```
GET /api/v1/tasks/my-status
```

**Description:** User gets their personal task assignment status

## Database Schema Requirements

### Tasks Table

-   `task_distribution_count` (integer): Counter for task assignments
-   `task_completion_count` (integer): Counter for task completions
-   `threshold_value` (integer): Maximum assignment/completion limit
-   `is_active` (boolean): Task availability status
-   `task_status` (string): Task status (active, inactive, etc.)

### TaskAssignments Table

-   `user_uuid` (string): User identifier
-   `task_id` (integer): Task identifier
-   `assigned_at` (datetime): Assignment timestamp
-   `expires_at` (datetime): Assignment expiration
-   `status` (string): Assignment status (pending, completed, expired)

### Membership Table

-   `tasks_per_day` (integer): Daily task quota for membership level
-   `is_active` (boolean): Membership availability status

### UserMemberships Table

-   `user_uuid` (string): User identifier
-   `membership_id` (integer): Membership identifier
-   `is_active` (boolean): Membership status
-   `expires_at` (datetime): Membership expiration

## Algorithm Logic Flow

1. **User Authentication & Membership Check**

    - Verify user has active membership
    - Get membership's `tasks_per_day` quota

2. **Daily Quota Check**

    - Count existing assignments for current day
    - Calculate remaining quota needed

3. **Task Filtering**

    - Get tasks with `task_completion_count <= threshold_value`
    - Get tasks with `task_distribution_count < threshold_value`
    - Exclude tasks already assigned to user
    - Order by priority and creation date

4. **Assignment Process**

    - Create TaskAssignment records
    - Increment `task_distribution_count` for each task
    - Use database transactions for data integrity

5. **Logging & Error Handling**
    - Comprehensive logging of all operations
    - Graceful error handling with rollback
    - Detailed error reporting

## Usage Examples

### Daily Assignment (Admin)

```php
$enhancedService = new EnhancedTaskAssignmentService();
$results = $enhancedService->assignDailyTasks();
```

### User Assignment

```php
$user = User::find($userId);
$assignedCount = $enhancedService->assignTasksToUser($user);
```

### Status Check

```php
$status = $enhancedService->getUserTaskStatus($user);
if ($status['can_receive_tasks']) {
    // User can receive more tasks
}
```

## Benefits

1. **Precise Control**: Exact implementation of all specified requirements
2. **Data Integrity**: Database transactions ensure consistency
3. **Scalability**: Efficient queries and proper indexing
4. **Monitoring**: Comprehensive logging and statistics
5. **Flexibility**: Easy to extend and modify
6. **Reliability**: Robust error handling and validation

## Migration from Existing System

The enhanced algorithm maintains compatibility with existing data structures while providing improved functionality. Existing task assignments continue to work while new assignments use the enhanced logic.

## Testing

The algorithm has been tested with:

-   Various membership levels and quotas
-   Task threshold scenarios
-   User assignment constraints
-   Error handling and edge cases
-   Database transaction integrity

## Future Enhancements

Potential improvements include:

-   Advanced priority algorithms
-   Dynamic quota adjustments
-   Performance optimizations
-   Enhanced reporting features
-   Integration with external systems
