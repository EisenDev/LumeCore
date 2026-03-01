<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'urls_and_sync',
        'display_name',
        'type', // document, website, repository, sync
        'primary_asset_id',
        'secondary_asset_id',
        'sync_status',
        'docu_and_urls_status',
        'sync_confidence_score',
        'individual_score',
        'vectors', // Added for fast forensic vector retrieval
        'scanned_at',
        'batch_id', // TITAN V2: Sync Batch ID
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'sync_confidence_score' => 'decimal:2',
        'individual_score' => 'decimal:2',
        'vectors' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function primaryAsset()
    {
        return $this->belongsTo(VaultAsset::class, 'primary_asset_id');
    }

    public function secondaryAsset()
    {
        return $this->belongsTo(VaultAsset::class, 'secondary_asset_id');
    }

    /**
     * Accessor for 'urls_and_sync'
     * Falls back to primary asset filename if column is null.
     */
    public function getUrlsAndSyncAttribute($value)
    {
        if (!empty($value)) return $value;

        // Fallback Logic
        if ($this->primaryAsset) {
            // Priority: Website URL -> File Name
            return $this->primaryAsset->website_url 
                ?? $this->primaryAsset->file_name 
                ?? $this->primaryAsset->metadata['target_url'] 
                ?? 'Unnamed Asset';
        }

        return 'Unnamed Asset';
    }

    protected $appends = ['urls_and_sync'];
}
