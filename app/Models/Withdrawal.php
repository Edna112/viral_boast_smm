<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Withdrawal extends Model
{
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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_uuid',
        'withdrawal_amount',
        'platform',
        'account_details',
        'wallet_address',
        'address_type',
        'picture_path',
        'is_completed',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uuid' => 'string',
        'withdrawal_amount' => 'decimal:2',
        'is_completed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID before creating
        static::creating(function ($withdrawal) {
            if (empty($withdrawal->uuid)) {
                $withdrawal->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the withdrawal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Scope a query to only include completed withdrawals.
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope a query to only include pending withdrawals.
     */
    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope a query to only include withdrawals from specific platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include withdrawals with minimum amount.
     */
    public function scopeMinimumAmount($query, $amount = 10)
    {
        return $query->where('withdrawal_amount', '>=', $amount);
    }

    /**
     * Get the picture URL attribute (already a URL).
     */
    public function getPictureUrlAttribute()
    {
        return $this->picture_path;
    }

    /**
     * Complete the withdrawal.
     */
    public function complete()
    {
        $this->update(['is_completed' => true]);
        return $this;
    }

    /**
     * Mark withdrawal as pending.
     */
    public function markPending()
    {
        $this->update(['is_completed' => false]);
        return $this;
    }

    /**
     * Get formatted withdrawal amount.
     */
    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->withdrawal_amount, 2);
    }
}
