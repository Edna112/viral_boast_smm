<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_uuid',
        'referred_user_uuid',
        'status',
        'completed_at',
        'referral_type',
        'bonus_amount',
        'bonus_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'bonus_paid_at' => 'datetime',
            'bonus_amount' => 'decimal:2',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_uuid', 'uuid');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_uuid', 'uuid');
    }

    /**
     * Scope for direct referrals
     */
    public function scopeDirect($query)
    {
        return $query->where('referral_type', 'direct');
    }

    /**
     * Scope for indirect referrals
     */
    public function scopeIndirect($query)
    {
        return $query->where('referral_type', 'indirect');
    }

    /**
     * Scope for completed referrals
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending referrals
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if referral bonus has been paid
     */
    public function isBonusPaid(): bool
    {
        return !is_null($this->bonus_paid_at);
    }

    /**
     * Mark bonus as paid
     */
    public function markBonusPaid(): void
    {
        $this->update([
            'bonus_paid_at' => now(),
            'status' => 'completed'
        ]);
    }
}
