<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VaultAsset extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'status',
        'metadata',
        'full_audit_report',
        'radar_data',
        'suggested_value',
        'is_for_sale',
        'price',
        'sale_count',
        'score',
        'sync_score',
        'synced_metadata',
        'website_metadata',
        'repository_metadata',
        'batch_id', // TITAN V2: Sync Batch ID
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'metadata' => 'array',
            'radar_data' => 'array',
            'synced_metadata' => 'array',
            'website_metadata' => 'array',
            'repository_metadata' => 'array',
        ];
    }

    /**
     * Get the user that owns the vault asset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditHistory(): HasMany
    {
        return $this->hasMany(AuditHistory::class)->orderBy('created_at', 'desc');
    }

    public function purchase(): HasOne
    {
        return $this->hasOne(Purchase::class);
    }

    /**
     * Get the latest sync activity where this asset was the primary target.
     * Used for Marketplace "Status" display.
     */
    public function latestSyncActivity(): HasOne
    {
        return $this->hasOne(ScanActivity::class, 'primary_asset_id')
            ->where('type', 'sync')
            ->orderBy('scanned_at', 'desc');
    }

    /**
     * Get the sibling asset in the same batch (e.g., Repo for a Web asset).
     */
    public function sibling(): HasOne
    {
        return $this->hasOne(VaultAsset::class, 'batch_id', 'batch_id')->where('id', '!=', $this->id);
    }
}
