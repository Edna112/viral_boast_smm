<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_uuid',
        'contact_type',
        'contact',
        'severity_level',
        'description',
        'admin_response',
        'is_active',
        'is_resolved',
        'assigned_to',
        'resolved_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $primaryKey = 'id';
    public $incrementing = true;

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the user who submitted this complaint (if not anonymous)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the admin assigned to handle this complaint
     */
    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'uuid');
    }

    /**
     * Scope for active complaints
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive complaints
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope for resolved complaints
     */
    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    /**
     * Scope for unresolved complaints
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope for complaints by severity level
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity_level', $severity);
    }

    /**
     * Scope for complaints by contact type
     */
    public function scopeByContactType($query, $contactType)
    {
        return $query->where('contact_type', $contactType);
    }

    /**
     * Scope for complaints by user
     */
    public function scopeByUser($query, $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * Scope for complaints assigned to admin
     */
    public function scopeAssignedTo($query, $adminUuid)
    {
        return $query->where('assigned_to', $adminUuid);
    }

    /**
     * Scope for unassigned complaints
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Check if complaint is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if complaint is resolved
     */
    public function isResolved(): bool
    {
        return $this->is_resolved;
    }

    /**
     * Check if complaint is assigned
     */
    public function isAssigned(): bool
    {
        return !is_null($this->assigned_to);
    }

    /**
     * Check if complaint is anonymous
     */
    public function isAnonymous(): bool
    {
        return is_null($this->user_uuid);
    }

    /**
     * Get the time since complaint was created
     */
    public function getTimeSinceCreatedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the time since complaint was resolved
     */
    public function getTimeSinceResolvedAttribute(): ?string
    {
        return $this->resolved_at ? $this->resolved_at->diffForHumans() : null;
    }

    /**
     * Get formatted creation date
     */
    public function getFormattedCreatedDateAttribute(): string
    {
        return $this->created_at->format('M j, Y \a\t g:i A');
    }

    /**
     * Get formatted resolution date
     */
    public function getFormattedResolvedDateAttribute(): ?string
    {
        return $this->resolved_at ? $this->resolved_at->format('M j, Y \a\t g:i A') : null;
    }

    /**
     * Get severity level badge color
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity_level) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }
        
        return $this->is_resolved ? 'Resolved' : 'Pending';
    }

    /**
     * Get priority score based on severity and age
     */
    public function getPriorityScoreAttribute(): int
    {
        $severityScores = [
            'low' => 1,
            'medium' => 2,
            'high' => 3
        ];

        $severityScore = $severityScores[$this->severity_level] ?? 1;
        $ageInDays = $this->created_at->diffInDays(now());
        
        // Higher score for older unresolved complaints
        $ageScore = min($ageInDays, 7); // Cap at 7 days
        
        return $severityScore + $ageScore;
    }

    /**
     * Mark complaint as resolved
     */
    public function markAsResolved(string $adminResponse = null, string $adminUuid = null): bool
    {
        $updateData = [
            'is_resolved' => true,
            'resolved_at' => now(),
        ];

        if ($adminResponse) {
            $updateData['admin_response'] = $adminResponse;
        }

        if ($adminUuid) {
            $updateData['assigned_to'] = $adminUuid;
        }

        return $this->update($updateData);
    }

    /**
     * Mark complaint as unresolved
     */
    public function markAsUnresolved(): bool
    {
        return $this->update([
            'is_resolved' => false,
            'resolved_at' => null,
        ]);
    }

    /**
     * Assign complaint to admin
     */
    public function assignTo(string $adminUuid): bool
    {
        return $this->update(['assigned_to' => $adminUuid]);
    }

    /**
     * Unassign complaint
     */
    public function unassign(): bool
    {
        return $this->update(['assigned_to' => null]);
    }

    /**
     * Deactivate complaint
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Activate complaint
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }
}