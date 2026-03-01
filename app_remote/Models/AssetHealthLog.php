<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetHealthLog extends Model
{
    protected $fillable = [
        'vault_asset_id',
        'check_type',
        'status',
        'response_time_ms',
        'message',
    ];

    public function vaultAsset(): BelongsTo
    {
        return $this->belongsTo(VaultAsset::class);
    }
}
