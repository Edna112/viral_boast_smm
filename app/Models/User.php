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
        'email_verification_code',
        'email_verification_expires_at',
        'phone_verification_code',
        'phone_verification_expires_at',
        'phone_verified_at',
        'referral_code',
        'referred_by',
        'total_points',
        'tasks_completed_today',
        'total_tasks',
        'max_direct_referrals',
        'direct_referrals_count',
        'indirect_referrals_count',
        'referral_bonus_earned',
        'direct_referral_bonus',
        'indirect_referral_bonus',
        'last_task_reset_date',
        'profile_picture',
        'profile_image',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_verification_expires_at' => 'datetime',
            'phone_verification_expires_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
            'show_activity' => 'boolean',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
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

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });

        static::created(function ($user) {
            // Assign basic membership to new user
            $user->assignBasicMembership();
        });
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
            ->where('is_active', true)
            ->first();

        if (!$basicMembership) {
            \Log::warning('Basic membership not found when assigning to user: ' . $this->uuid);
            return;
        }

        // Check if user already has this membership
        $existingMembership = $this->memberships()
            ->where('membership_id', $basicMembership->id)
            ->first();

        if ($existingMembership) {
            // Update existing membership to active
            $this->memberships()->updateExistingPivot($basicMembership->id, [
                'is_active' => true,
                'started_at' => now(),
                'expires_at' => null, // Basic membership doesn't expire
                'daily_tasks_completed' => 0,
                'last_reset_date' => now()->toDateString(),
            ]);
        } else {
            // Create new membership assignment
            $this->memberships()->attach($basicMembership->id, [
                'started_at' => now(),
                'expires_at' => null, // Basic membership doesn't expire
                'is_active' => true,
                'daily_tasks_completed' => 0,
                'last_reset_date' => now()->toDateString(),
            ]);
        }

        \Log::info('Basic membership assigned to user: ' . $this->uuid);
    }

    /**
     * Get user's current active membership
     */
    public function getCurrentMembership(): ?Membership
    {
        return $this->memberships()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
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
        return $this->tasks_completed_today < $dailyLimit;
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
            'tasks_completed_today' => $this->tasks_completed_today,
            'last_task_reset_date' => $this->last_task_reset_date,
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
            'tasks_completed_today' => 0,
            'last_task_reset_date' => now()->toDateString(),
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
            ->where('is_active', true)
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
            ->where('is_active', true)
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

        if (!$referrer->is_active) {
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
                'is_active' => $referrer->is_active,
                'max_direct_referrals' => $referrer->max_direct_referrals,
                'direct_referrals_count' => $referrer->direct_referrals_count,
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
                $query->where('is_active', true);
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
     * Check if user can make more direct referrals
     */
    public function canMakeDirectReferral(): bool
    {
        return $this->direct_referrals_count < $this->max_direct_referrals;
    }

    /**
     * Get remaining direct referrals allowed
     */
    public function getRemainingDirectReferrals(): int
    {
        return max(0, $this->max_direct_referrals - $this->direct_referrals_count);
    }

    /**
     * Process referral bonus for direct referral
     */
    public function processDirectReferralBonus(User $referredUser): void
    {
        if (!$this->canMakeDirectReferral()) {
            \Log::warning("User {$this->uuid} has reached maximum direct referrals limit");
            return;
        }

        // Create direct referral record
        $referral = Referral::create([
            'referrer_uuid' => $this->uuid,
            'referred_user_uuid' => $referredUser->uuid,
            'referral_type' => 'direct',
            'bonus_amount' => $this->direct_referral_bonus,
            'status' => 'completed',
        ]);

        // Update user counts and bonus
        $this->increment('direct_referrals_count');
        $this->increment('referral_bonus_earned', $this->direct_referral_bonus);
        $this->increment('total_points', $this->direct_referral_bonus);

        // Mark bonus as paid
        $referral->markBonusPaid();

        \Log::info("Direct referral bonus processed: {$this->direct_referral_bonus} for user {$this->uuid}");
    }

    /**
     * Process referral bonus for indirect referral (Level 1)
     */
    public function processIndirectReferralBonus(User $referredUser): void
    {
        // Create indirect referral record
        $referral = Referral::create([
            'referrer_uuid' => $this->uuid,
            'referred_user_uuid' => $referredUser->uuid,
            'referral_type' => 'indirect',
            'bonus_amount' => $this->indirect_referral_bonus,
            'status' => 'completed',
        ]);

        // Update user counts and bonus
        $this->increment('indirect_referrals_count');
        $this->increment('referral_bonus_earned', $this->indirect_referral_bonus);
        $this->increment('total_points', $this->indirect_referral_bonus);

        // Mark bonus as paid
        $referral->markBonusPaid();

        \Log::info("Indirect referral bonus processed: {$this->indirect_referral_bonus} for user {$this->uuid}");
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
            'max_direct_referrals' => $this->max_direct_referrals,
            'direct_referrals_count' => $this->direct_referrals_count,
            'indirect_referrals_count' => $this->indirect_referrals_count,
            'remaining_direct_referrals' => $this->getRemainingDirectReferrals(),
            'can_make_direct_referral' => $this->canMakeDirectReferral(),
            'referral_bonus_earned' => $this->referral_bonus_earned,
            'direct_referral_bonus' => $this->direct_referral_bonus,
            'indirect_referral_bonus' => $this->indirect_referral_bonus,
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
        
        if (!$referrer->canMakeDirectReferral()) {
            return [
                'valid' => false,
                'message' => 'This referrer has reached their maximum direct referrals limit',
                'error' => 'REFERRER_LIMIT_REACHED',
                'referrer' => [
                    'name' => $referrer->name,
                    'max_direct_referrals' => $referrer->max_direct_referrals,
                    'direct_referrals_count' => $referrer->direct_referrals_count,
                    'remaining_referrals' => $referrer->getRemainingDirectReferrals(),
                ]
            ];
        }

        return $validation;
    }
}
