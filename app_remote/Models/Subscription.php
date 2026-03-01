<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'plan_type',
        'status',
        'daily_individual_scans_used',
        'daily_sync_scans_used',
        'daily_rescans_used',
        'monthly_rescans_used',
        'monthly_pentests_used',
        'last_daily_reset_at',
        'last_monthly_reset_at',
        'trial_ends_at',
        'ends_at',
    ];

    protected $casts = [
        'last_daily_reset_at' => 'datetime',
        'last_monthly_reset_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the subscription has remaining quota for a specific action.
     */
    public function hasQuota(string $type): bool
    {
        $this->checkAndResetLimits();

        $planType = strtolower($this->plan_type ?? '');
        if ($planType === 'agency') {
            if ($type === 'pentest') {
                return $this->monthly_pentests_used < 100;
            }
            if ($type === 'rescan') {
                return $this->daily_rescans_used < 100;
            }
            return true; // Unlimited scans for Agency
        }

        if ($planType === 'developer') {
            return match($type) {
                'document' => $this->daily_individual_scans_used < 20,
                'project' => $this->daily_individual_scans_used < 20,
                'sync' => $this->daily_sync_scans_used < 10,
                'pentest' => $this->monthly_pentests_used < 4,
                'rescan' => $this->daily_rescans_used < 20,
                default => false,
            };
        }

        return false;
    }

    /**
     * Increment the usage for a specific action.
     */
    public function incrementUsage(string $type): void
    {
        $this->checkAndResetLimits();

        if ($type === 'pentest') {
            $this->increment('monthly_pentests_used');
        } elseif ($type === 'rescan') {
            $this->increment('daily_rescans_used');
        } elseif ($type === 'sync') {
            $this->increment('daily_sync_scans_used');
        } else {
            $this->increment('daily_individual_scans_used');
        }
    }

    /**
     * Decrement the usage for a specific action (refund quota).
     */
    public function decrementUsage(string $type): void
    {
        if ($type === 'pentest' && $this->monthly_pentests_used > 0) {
            $this->decrement('monthly_pentests_used');
        } elseif ($type === 'rescan' && $this->daily_rescans_used > 0) {
            $this->decrement('daily_rescans_used');
        } elseif ($type === 'sync' && $this->daily_sync_scans_used > 0) {
            $this->decrement('daily_sync_scans_used');
        } elseif ($this->daily_individual_scans_used > 0) {
            $this->decrement('daily_individual_scans_used');
        }
        
        \Log::info("Subscription Usage Decremented: {$type} for subscription {$this->id}");
    }

    /**
     * Reset daily and monthly limits if period has passed.
     */
    protected function checkAndResetLimits(): void
    {
        $now = now();

        // Daily Reset
        if (!$this->last_daily_reset_at || $this->last_daily_reset_at->isBefore($now->startOfDay())) {
            $this->update([
                'daily_individual_scans_used' => 0,
                'daily_sync_scans_used' => 0,
                'daily_rescans_used' => 0,
                'last_daily_reset_at' => $now,
            ]);
        }

        // Monthly Reset
        if (!$this->last_monthly_reset_at || $this->last_monthly_reset_at->isBefore($now->startOfMonth())) {
            $this->update([
                'monthly_pentests_used' => 0,
                'monthly_rescans_used' => 0,
                'last_monthly_reset_at' => $now,
            ]);
        }
    }
}
