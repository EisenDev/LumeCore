<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_asset_id',
        'buyer_id',
        'seller_id',
        'amount',
        'currency',
        'status',
        'escrow_type',
        'payment_confirmed_at',
        'locked_at',
        'release_at',
        'transfer_started_at',
        'transfer_completed_at',
        'transfer_accepted_at',
        'released_at',
        'buyer_github_username',
        'buyer_accepted_transfer',
        'transfer_log',
        'payment_intent_id',
        'payment_method',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
        'release_at' => 'datetime',
        'transfer_started_at' => 'datetime',
        'transfer_completed_at' => 'datetime',
        'transfer_accepted_at' => 'datetime',
        'released_at' => 'datetime',
        'transfer_log' => 'array',
        'buyer_accepted_transfer' => 'boolean',
    ];

    /**
     * Get the project being sold.
     */
    public function projectAsset(): BelongsTo
    {
        return $this->belongsTo(ProjectAsset::class);
    }

    /**
     * Get the buyer.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Lock the escrow (7-day hold starts).
     */
    public function lock(): void
    {
        $this->update([
            'status' => 'locked',
            'locked_at' => now(),
            'release_at' => now()->addDays(7),
        ]);
    }

    /**
     * Check if escrow is ready for release.
     */
    public function isReadyForRelease(): bool
    {
        return $this->status === 'locked' && 
               $this->release_at && 
               now()->gte($this->release_at);
    }

    /**
     * Check if escrow is in transferring state.
     */
    public function isTransferring(): bool
    {
        return $this->status === 'transferring';
    }

    /**
     * Add entry to transfer log.
     */
    public function addTransferLog(string $message, string $status = 'info'): void
    {
        $log = $this->transfer_log ?? [];
        $log[] = [
            'timestamp' => now()->toIso8601String(),
            'message' => $message,
            'status' => $status,
        ];
        $this->update(['transfer_log' => $log]);
    }

    /**
     * Get days remaining until release.
     */
    public function getDaysUntilReleaseAttribute(): ?int
    {
        if (!$this->release_at) {
            return null;
        }
        
        $days = now()->diffInDays($this->release_at, false);
        return max(0, $days);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending_payment' => 'Awaiting Payment',
            'locked' => 'Escrow Locked',
            'transferring' => 'Transferring Assets',
            'completed' => 'Completed',
            'refunded' => 'Refunded',
            'disputed' => 'Under Dispute',
            default => ucfirst($this->status),
        };
    }
}
