# Task Distribution Algorithm Flow Diagram

## Main Distribution Flow (User-Specific)

```
┌─────────────────┐
│   User Request  │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Authenticate    │
│ User & Validate │
│ Membership      │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Check Existing  │
│ Daily Tasks     │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Calculate Tasks │
│ Needed Today    │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Filter Available│
│ Tasks by:       │
│ • Active Status │
│ • Threshold     │
│ • Not Completed │
│ • Priority      │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Create Task     │
│ Assignments     │
│ • 24hr Expiry   │
│ • Track Counts  │
│ • Prevent Dups  │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Fallback: Get   │
│ Remaining Tasks │
│ (Relax Threshold)│
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Update User     │
│ Records &       │
│ Return Response │
└─────────────────┘
```

## Bulk Distribution Flow (TaskDistributionService)

```
┌─────────────────┐
│ Start Bulk      │
│ Distribution    │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Get Memberships │
│ Ordered by      │
│ Price (Desc)    │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ For Each        │
│ Membership:     │
│ • Get Eligible  │
│   Users         │
│ • Check Daily   │
│   Limits        │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Distribute      │
│ Tasks to Users  │
│ • Max 3 per     │
│   User          │
│ • Create        │
│   Assignments   │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Update Task     │
│ Distribution    │
│ Counts          │
└─────────────────┘
```

## Task Filtering Logic

```
Available Tasks Pool
         │
         ▼
┌─────────────────┐
│ Filter by:      │
│ • is_active     │
│ • task_status   │
│ • thresholds    │
│ • user history  │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Sort by:        │
│ • Priority      │
│   (urgent→low)  │
│ • Creation Date │
│   (oldest first)│
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ Limit by:       │
│ • Tasks Needed  │
│ • User Capacity │
└─────────────────┘
```

## Key Components

### Task Assignment Creation

```
Task Assignment Record:
├── user_uuid
├── task_id
├── status: 'pending'
├── assigned_at: now()
├── expires_at: now() + 24hrs
├── base_points: task.benefit
├── vip_multiplier: 1.0
└── final_reward: calculated
```

### Threshold Management

```
Task Thresholds:
├── task_distribution_count < threshold_value
├── task_completion_count < threshold_value
└── Prevents over-distribution
```

### Priority System

```
Priority Levels:
├── urgent (1)
├── high (2)
├── medium (3)
└── low (4)
```

### Daily Limits

```
User Daily Limits:
├── membership.tasks_per_day
├── Tracked per user
├── Reset daily
└── Prevents over-assignment
```
