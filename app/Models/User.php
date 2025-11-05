<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uuid' => 'string',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'password',
        'profile_image',
        'assigned_tasks',
        'completed_tasks',
        'inprogress_tasks',
        'email_verified_at',
        'email_verification_code',
        'email_verification_expires_at',
        'phone_verified_at',
        'phone_verification_code',
        'phone_verification_expires_at',
        'referral_code',
        'referred_by',
        'total_points',
        'total_tasks',
        'tasks_completed_today',
        'last_task_reset_date',
        'tasks_submitted_today',
        'last_submission_reset_date',
        'account_balance',
        'is_active',
        'is_admin',
        'deactivated_at',
        'deactivation_reason',
        'profile_visibility',
        'show_email',
        'show_phone',
        'show_activity',
            'email_notifications',
            'sms_notifications',
            'two_factor_enabled',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'membership_level',
            'role',
            'isActive',
            'lastLogin',
        ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
        'phone_verification_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_verification_expires_at' => 'datetime',
            'total_points' => 'decimal:2',
            'total_tasks' => 'integer',
            'tasks_completed_today' => 'integer',
            'last_task_reset_date' => 'date',
            'account_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'deactivated_at' => 'datetime',
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
            'show_activity' => 'boolean',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'isActive' => 'boolean',
            'lastLogin' => 'datetime',
            'assigned_tasks' => 'array',
            'completed_tasks' => 'array',
            'inprogress_tasks' => 'array',
        ];
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID before creating
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });

        // Generate referral code before creating
        static::creating(function ($user) {
            if (empty($user->referral_code)) {
                $user->referral_code = $user->generateUniqueReferralCode();
            }
        });

        // Custom validation: At least one of email or phone must be provided
        static::creating(function ($user) {
            if (empty($user->email) && empty($user->phone)) {
                throw new \Exception('Either email or phone number is required');
            }
        });
    }

    /**
     * Remove password from JSON output
     */
    public function toArray()
    {
        $array = parent::toArray();
        unset($array['password']);
        return $array;
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param  string  $name
     * @param  array  $abilities
     * @param  \DateTimeInterface|null  $expiresAt
     * @return \Laravel\Sanctum\NewAccessToken
     */
    public function createToken(string $name, array $abilities = ['*'], \DateTimeInterface $expiresAt = null)
    {
        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken = \Illuminate\Support\Str::random(40)),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new \Laravel\Sanctum\NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }


    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_uuid', 'uuid');
    }

    public function referredBy(): HasMany
    {
        return $this->hasMany(Referral::class, 'referred_user_uuid', 'uuid');
    }

    public function account()
    {
        return $this->hasOne(Account::class, 'user_uuid', 'uuid');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'user_uuid', 'uuid');
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class, 'user_uuid', 'uuid');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_level', 'id');
    }

    public function memberships()
    {
        return $this->belongsToMany(Membership::class, 'user_memberships')
                    ->withPivot(['started_at', 'expires_at', 'is_active', 'daily_tasks_completed', 'last_reset_date'])
                    ->withTimestamps();
    }

    public function activeMembership()
    {
        return $this->memberships()
                    ->wherePivot('is_active', true)
                    ->wherePivot('expires_at', '>', now());
    }

    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function pendingTasks()
    {
        return $this->taskAssignments()->where('status', 'pending');
    }

    public function completedTasks()
    {
        return $this->taskAssignments()->where('status', 'completed');
    }

    /**
     * Get user's pending task assignments
     */
    public function pendingTaskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->where('status', 'pending');
    }

    /**
     * Get user's completed task assignments
     */
    public function completedTaskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->where('status', 'completed');
    }

    /**
     * Get user's push notification subscriptions
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'user_uuid', 'uuid');
    }

    /**
     * Check if user has active VIP membership
     */
    public function hasActiveVipMembership(): bool
    {
        return $this->activeVipMembership()->exists();
    }

    /**
     * Get user's VIP level
     */
    public function getVipLevel(): ?string
    {
        $membership = $this->currentVipMembership();
        return $membership ? $membership->vipMembership->slug : 'basic';
    }

    /**
     * Get user's VIP multiplier
     */
    public function getVipMultiplier(): float
    {
        $membership = $this->currentVipMembership();
        return $membership ? $membership->vipMembership->reward_multiplier : 1.0;
    }

    /**
     * Assign basic membership to user
     */
    public function assignBasicMembership(): void
    {
        // Get the basic membership
        $basicMembership = Membership::where('membership_name', 'Basic')
            ->where('isActive', true)
            ->first();

        if (!$basicMembership) {
            \Log::warning('Basic membership not found when assigning to user: ' . $this->uuid);
            return;
        }

        // Assign the basic membership directly to the user
        $this->update(['membership_level' => $basicMembership->id]);
        
        \Log::info('Basic membership assigned to user: ' . $this->uuid);
    }

    /**
     * Get user's current active membership
     */
    public function getCurrentMembership(): ?Membership
    {
        return $this->membership;
    }

    /**
     * Get user's membership level name
     */
    public function getMembershipLevel(): string
    {
        $membership = $this->getCurrentMembership();
        return $membership ? $membership->membership_name : 'Basic';
    }

    /**
     * Get user's daily task limit based on membership
     */
    public function getDailyTaskLimit(): int
    {
        $membership = $this->getCurrentMembership();
        return $membership ? $membership->tasks_per_day : 5; // Default to 5 for basic
    }

    /**
     * Check if user can complete more tasks today
     */
    public function canCompleteMoreTasks(): bool
    {
        $dailyLimit = $this->getDailyTaskLimit();
        return $this->total_completed_today < $dailyLimit;
    }

    /**
     * Increment total tasks completed by user
     */
    public function incrementTotalTasks(): void
    {
        $this->increment('total_tasks');
    }

    /**
     * Get user's task completion statistics
     */
    public function getTaskStats(): array
    {
        return [
            'total_tasks' => $this->total_tasks,
            'total_completed_today' => $this->total_completed_today,
            'daily_task_limit' => $this->getDailyTaskLimit(),
            'can_complete_more_tasks' => $this->canCompleteMoreTasks(),
            'membership_level' => $this->getMembershipLevel(),
        ];
    }

    /**
     * Reset daily task count (for daily cron jobs)
     */
    public function resetDailyTaskCount(): void
    {
        $this->update([
            'total_completed_today' => 0,
        ]);
    }

    /**
     * Check if a referral code is valid
     */
    public static function isValidReferralCode(string $referralCode): bool
    {
        if (empty($referralCode)) {
            return false;
        }

        return self::where('referral_code', $referralCode)
            ->where('isActive', true)
            ->exists();
    }

    /**
     * Get user by referral code
     */
    public static function getByReferralCode(string $referralCode): ?User
    {
        if (empty($referralCode)) {
            return null;
        }

        return self::where('referral_code', $referralCode)
            ->where('isActive', true)
            ->first();
    }

    /**
     * Validate referral code with detailed response
     */
    public static function validateReferralCode(string $referralCode): array
    {
        if (empty($referralCode)) {
            return [
                'valid' => false,
                'message' => 'Referral code is required',
                'error' => 'EMPTY_REFERRAL_CODE'
            ];
        }

        if (strlen($referralCode) < 3 || strlen($referralCode) > 20) {
            return [
                'valid' => false,
                'message' => 'Referral code must be between 3 and 20 characters',
                'error' => 'INVALID_LENGTH'
            ];
        }

        $referrer = self::getByReferralCode($referralCode);
        
        if (!$referrer) {
            return [
                'valid' => false,
                'message' => 'Invalid referral code',
                'error' => 'INVALID_REFERRAL_CODE'
            ];
        }

        if (!$referrer->isActive) {
            return [
                'valid' => false,
                'message' => 'Referral code belongs to an inactive user',
                'error' => 'INACTIVE_REFERRER'
            ];
        }

        // Note: deleted_at check removed as soft deletes are not implemented

        return [
            'valid' => true,
            'message' => 'Valid referral code',
            'referrer' => [
                'uuid' => $referrer->uuid,
                'name' => $referrer->name,
                'referral_code' => $referrer->referral_code,
                'total_referrals' => $referrer->referrals()->count(),
                'isActive' => $referrer->isActive,
                'remaining_referrals' => $referrer->getRemainingDirectReferrals(),
            ]
        ];
    }

    /**
     * Check if user can use this referral code (prevents self-referral)
     */
    public function canUseReferralCode(string $referralCode): bool
    {
        if ($this->referral_code === $referralCode) {
            return false; // Can't refer yourself
        }

        return self::isValidReferralCode($referralCode);
    }

    /**
     * Get referral statistics for a user
     */
    public function getReferralStats(): array
    {
        $totalReferrals = $this->referrals()->count();
        $activeReferrals = $this->referrals()
            ->whereHas('referredUser', function ($query) {
                $query->where('isActive', true);
            })
            ->count();

        return [
            'referral_code' => $this->referral_code,
            'total_referrals' => $totalReferrals,
            'active_referrals' => $activeReferrals,
            'referral_url' => url('/register?ref=' . $this->referral_code),
            'referrer_name' => $this->referred_by ? $this->referrer->name : null,
            'referrer_code' => $this->referred_by ? $this->referrer->referral_code : null,
        ];
    }

    /**
     * Generate a unique referral code
     */
    public static function generateUniqueReferralCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Check if user can make more direct referrals (simplified for new schema)
     */
    public function canMakeDirectReferral(): bool
    {
        return true; // No limits in simplified schema
    }

    /**
     * Get remaining direct referrals allowed (simplified for new schema)
     */
    public function getRemainingDirectReferrals(): int
    {
        return 999; // No limits in simplified schema
    }

    /**
     * Process referral bonus for direct referral (simplified for new schema)
     */
    public function processDirectReferralBonus(User $referredUser): void
    {
        // Create direct referral record
        $referral = Referral::create([
            'referrer_uuid' => $this->uuid,
            'referred_user_uuid' => $referredUser->uuid,
            'referral_type' => 'direct',
            'bonus_amount' => 5.00, // Fixed bonus amount
            'status' => 'completed',
        ]);

        // Mark bonus as paid
        $referral->markBonusPaid();

        \Log::info("Direct referral bonus processed: 5.00 for user {$this->uuid}");
    }

    /**
     * Process referral bonus for indirect referral (simplified for new schema)
     */
    public function processIndirectReferralBonus(User $referredUser): void
    {
        // Create indirect referral record
        $referral = Referral::create([
            'referrer_uuid' => $this->uuid,
            'referred_user_uuid' => $referredUser->uuid,
            'referral_type' => 'indirect',
            'bonus_amount' => 2.50, // Fixed bonus amount
            'status' => 'completed',
        ]);

        // Mark bonus as paid
        $referral->markBonusPaid();

        \Log::info("Indirect referral bonus processed: 2.50 for user {$this->uuid}");
    }

    /**
     * Get comprehensive referral statistics
     */
    public function getComprehensiveReferralStats(): array
    {
        $directReferrals = $this->referrals()->direct()->completed()->count();
        $indirectReferrals = $this->referrals()->indirect()->completed()->count();
        $pendingReferrals = $this->referrals()->pending()->count();

        return [
            'referral_code' => $this->referral_code,
            'remaining_direct_referrals' => $this->getRemainingDirectReferrals(),
            'can_make_direct_referral' => $this->canMakeDirectReferral(),
            'total_referrals' => $directReferrals + $indirectReferrals,
            'pending_referrals' => $pendingReferrals,
            'referral_url' => url('/register?ref=' . $this->referral_code),
        ];
    }

    /**
     * Validate referral code with limits check
     */
    public static function validateReferralCodeWithLimits(string $referralCode): array
    {
        $validation = self::validateReferralCode($referralCode);
        
        if (!$validation['valid']) {
            return $validation;
        }

        $referrer = self::getByReferralCode($referralCode);
        
        // No limits in simplified schema, so always return valid

        return $validation;
    }

    /**
     * Add tasks to assigned_tasks array
     */
    public function addAssignedTasks(array $tasks): void
    {
        $currentTasks = $this->assigned_tasks ?? [];
        $this->update(['assigned_tasks' => array_merge($currentTasks, $tasks)]);
    }

    /**
     * Move task from assigned_tasks to completed_tasks
     */
    public function moveTaskToCompleted(int $taskId): void
    {
        $assignedTasks = $this->assigned_tasks ?? [];
        $completedTasks = $this->completed_tasks ?? [];
        
        // Find the task in assigned_tasks
        $taskToMove = null;
        $remainingAssigned = [];
        
        foreach ($assignedTasks as $task) {
            if ($task['id'] == $taskId) {
                $taskToMove = $task;
                // Add completion timestamp
                $taskToMove['completed_at'] = now()->toISOString();
                $taskToMove['status'] = 'completed';
            } else {
                $remainingAssigned[] = $task;
            }
        }
        
        if ($taskToMove) {
            // Add to completed_tasks
            $completedTasks[] = $taskToMove;
            
            // Update both arrays
            $this->update([
                'assigned_tasks' => $remainingAssigned,
                'completed_tasks' => $completedTasks
            ]);
        }
    }

    /**
     * Get assigned tasks count
     */
    public function getAssignedTasksCount(): int
    {
        return count($this->assigned_tasks ?? []);
    }

    /**
     * Get completed tasks count
     */
    public function getCompletedTasksCount(): int
    {
        return count($this->completed_tasks ?? []);
    }

    /**
     * Get in-progress tasks count
     */
    public function getInProgressTasksCount(): int
    {
        return count($this->inprogress_tasks ?? []);
    }

    /**
     * Clear all task arrays (for daily reset)
     */
    public function clearTaskArrays(): void
    {
        $this->update([
            'assigned_tasks' => [],
            'completed_tasks' => [],
            'inprogress_tasks' => []
        ]);
    }

    /**
     * Increment daily task submission count
     */
    public function incrementDailySubmissions(): void
    {
        $this->checkAndResetDailySubmissions();
        $this->increment('tasks_submitted_today');
    }

    /**
     * Check if daily submission count needs to be reset
     */
    public function checkAndResetDailySubmissions(): void
    {
        $today = today()->toDateString();
        
        if (!$this->last_submission_reset_date || $this->last_submission_reset_date < $today) {
            $this->update([
                'tasks_submitted_today' => 0,
                'last_submission_reset_date' => $today
            ]);
        }
    }

    /**
     * Get daily submission count
     */
    public function getDailySubmissionsCount(): int
    {
        $this->checkAndResetDailySubmissions();
        return $this->tasks_submitted_today;
    }

    /**
     * Check if user has reached daily submission limit
     */
    public function hasReachedDailySubmissionLimit(): bool
    {
        $this->checkAndResetDailySubmissions();
        $membership = $this->membership;
        
        if (!$membership) {
            return true; // No membership = no submissions allowed
        }
        
        return $this->tasks_submitted_today >= $membership->tasks_per_day;
    }
}
