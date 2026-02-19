<?php

namespace App\Events;

use App\Models\VaultAsset;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssetStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The asset that was updated.
     */
    public VaultAsset $asset;

    /**
     * Create a new event instance.
     */
    public function __construct(VaultAsset $asset)
    {
        $this->asset = $asset;
    }

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
    }

    /**
     * Get the data to broadcast with the event.
     * This ensures the UI gets all necessary data for instant updates.
     */
    public function broadcastWith(): array
    {
        return [
            'asset' => [
                'id' => $this->asset->id,
                'file_name' => $this->asset->file_name,
                'file_path' => $this->asset->file_path,
                'file_size' => $this->asset->file_size,
                'mime_type' => $this->asset->mime_type,
                'status' => $this->asset->status,
                'metadata' => [
                    'website_url' => $this->asset->metadata['website_url'] ?? null,
                    'project_asset_id' => $this->asset->metadata['project_asset_id'] ?? null,
                    'summary' => $this->asset->metadata['executive_summary'] ?? ($this->asset->metadata['summary'] ?? ''),
                    'score' => $this->asset->score
                ],
                'radar_data' => $this->asset->radar_data, // Fix: Ensure chart data is broadcasted
                'suggested_value' => $this->asset->suggested_value,
                'is_for_sale' => $this->asset->is_for_sale,
                'price' => $this->asset->price,
                'created_at' => $this->asset->created_at->toIso8601String(),
                'updated_at' => $this->asset->updated_at->toIso8601String(),
            ],
        ];
    }
}

