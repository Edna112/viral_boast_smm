<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
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
        'picture_path',
        'platform',
        'amount',
        'description',
        'is_approved',
        'conversion_amount',
        'conversion_currency',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uuid' => 'string',
        'amount' => 'decimal:2',
        'is_approved' => 'boolean',
        'conversion_amount' => 'decimal:8',
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
        static::creating(function ($payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Scope a query to only include approved payments.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    /**
     * Scope a query to only include payments with minimum amount.
     */
    public function scopeMinimumAmount($query, $amount = 10)
    {
        return $query->where('amount', '>=', $amount);
    }

    /**
     * Scope a query to only include payments from specific platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Get the picture URL attribute (already a URL).
     */
    public function getPictureUrlAttribute()
    {
        return $this->picture_path;
    }

    /**
     * Calculate conversion amount based on currency type.
     */
    public function calculateConversion($currency = 'bitcoin')
    {
        // Mock conversion rates - in real app, you'd fetch from an API
        $rates = [
            'USD' => 1.0,        // 1 USD = 1 USD
            'bitcoin' => 0.000023, // 1 USD = 0.000023 BTC
            'ethereum' => 0.0004,  // 1 USD = 0.0004 ETH
            'btc' => 0.000023,     // Same as bitcoin
        ];

        if (isset($rates[$currency])) {
            $this->conversion_amount = $this->amount * $rates[$currency];
            $this->conversion_currency = $currency;
            $this->save();
        }

        return $this;
    }

    /**
     * Approve the payment.
     */
    public function approve()
    {
        $this->update(['is_approved' => true]);
        return $this;
    }

    /**
     * Reject the payment.
     */
    public function reject()
    {
        $this->update(['is_approved' => false]);
        return $this;
    }

    /**
     * Validate minimum amount.
     */
    public static function validateMinimumAmount($amount)
    {
        return $amount >= 10;
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    /**
     * Get formatted conversion amount.
     */
    public function getFormattedConversionAttribute()
    {
        if ($this->conversion_amount && $this->conversion_currency) {
            return number_format($this->conversion_amount, 8) . ' ' . strtoupper($this->conversion_currency);
        }
        return null;
    }
}
