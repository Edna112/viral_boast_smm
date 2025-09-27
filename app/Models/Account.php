<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_uuid',
        'balance',
        'total_bonus',
        'total_withdrawals',
        'tasks_income',
        'referral_income',
        'total_earned',
        'is_active',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'total_bonus' => 'decimal:2',
            'total_withdrawals' => 'decimal:2',
            'tasks_income' => 'decimal:2',
            'referral_income' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'is_active' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive accounts
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Add funds to account balance
     */
    public function addFunds(float $amount, string $type = 'general'): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $this->increment('balance', $amount);
        $this->increment('total_earned', $amount);
        $this->updateLastActivity();

        // Update specific income type
        switch ($type) {
            case 'bonus':
                $this->increment('total_bonus', $amount);
                break;
            case 'referral':
                $this->increment('referral_income', $amount);
                break;
            case 'task':
                $this->increment('tasks_income', $amount);
                break;
        }

        \Log::info("Added {$amount} to account {$this->id} for user {$this->user_uuid} (type: {$type})");
        return true;
    }

    /**
     * Deduct funds from account balance
     */
    public function deductFunds(float $amount, string $reason = 'withdrawal'): bool
    {
        if ($amount <= 0 || $this->balance < $amount) {
            return false;
        }

        $this->decrement('balance', $amount);
        $this->updateLastActivity();

        if ($reason === 'withdrawal') {
            $this->increment('total_withdrawals', $amount);
        }

        \Log::info("Deducted {$amount} from account {$this->id} for user {$this->user_uuid} (reason: {$reason})");
        return true;
    }

    /**
     * Transfer funds to another account
     */
    public function transferTo(Account $targetAccount, float $amount, string $description = ''): bool
    {
        if ($amount <= 0 || $this->balance < $amount) {
            return false;
        }

        // Deduct from source account
        if (!$this->deductFunds($amount, 'transfer')) {
            return false;
        }

        // Add to target account
        if (!$targetAccount->addFunds($amount, 'transfer')) {
            // Rollback if target account addition fails
            $this->addFunds($amount, 'rollback');
            return false;
        }

        \Log::info("Transferred {$amount} from account {$this->id} to account {$targetAccount->id}");
        return true;
    }

    /**
     * Check if account has sufficient balance
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get available balance (balance minus pending withdrawals)
     */
    public function getAvailableBalance(): float
    {
        return $this->balance;
    }

    /**
     * Get account summary
     */
    public function getAccountSummary(): array
    {
        return [
            'account_id' => $this->id,
            'user_uuid' => $this->user_uuid,
            'balance' => $this->balance,
            'total_bonus' => $this->total_bonus,
            'total_withdrawals' => $this->total_withdrawals,
            'tasks_income' => $this->tasks_income,
            'referral_income' => $this->referral_income,
            'total_earned' => $this->total_earned,
            'is_active' => $this->is_active,
            'last_activity' => $this->last_activity_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get financial statistics
     */
    public function getFinancialStats(): array
    {
        return [
            'current_balance' => $this->balance,
            'total_earned' => $this->total_earned,
            'total_withdrawn' => $this->total_withdrawals,
            'net_worth' => $this->balance,
            'income_breakdown' => [
                'tasks' => $this->tasks_income,
                'referrals' => $this->referral_income,
                'bonuses' => $this->total_bonus,
            ],
            'withdrawal_rate' => $this->total_earned > 0 ? 
                round(($this->total_withdrawals / $this->total_earned) * 100, 2) : 0,
        ];
    }

    /**
     * Update last activity timestamp
     */
    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Deactivate account
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Activate account
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Create account for user
     */
    public static function createForUser(string $userUuid): Account
    {
        return self::create([
            'user_uuid' => $userUuid,
            'balance' => 0.00,
            'total_bonus' => 0.00,
            'total_withdrawals' => 0.00,
            'tasks_income' => 0.00,
            'referral_income' => 0.00,
            'total_earned' => 0.00,
            'is_active' => true,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Get or create account for user
     */
    public static function getOrCreateForUser(string $userUuid): Account
    {
        return self::firstOrCreate(
            ['user_uuid' => $userUuid],
            [
                'balance' => 0.00,
                'total_bonus' => 0.00,
                'total_withdrawals' => 0.00,
                'tasks_income' => 0.00,
                'referral_income' => 0.00,
                'total_earned' => 0.00,
                'is_active' => true,
                'last_activity_at' => now(),
            ]
        );
    }
}