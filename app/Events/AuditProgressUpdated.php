<?php

namespace App\Events;

use App\Models\VaultAsset;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AuditProgressUpdated Event
 * 
 * Broadcasts real-time progress updates during document/project auditing.
 * Uses ShouldBroadcastNow to send immediately without queuing.
 */
class AuditProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public VaultAsset $asset,
        public string $step,
        public int $progress,
        public string $status = 'processing',
        public ?string $details = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->asset->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'AuditProgressUpdated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'asset_id' => $this->asset->id,
            'step' => $this->step,
            'progress' => $this->progress,
            'status' => $this->status,
            // 'details' => $this->details, // TITAN FIX: Removed to prevent payload too large errors
        ];
    }
}
