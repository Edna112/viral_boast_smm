<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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
        'last_task_reset_date',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_verification_expires_at' => 'datetime',
            'phone_verification_expires_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy(): HasMany
    {
        return $this->hasMany(Referral::class, 'referred_user_id');
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
                    ->wherePivot('expires_at', '>', now())
                    ->first();
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
}
