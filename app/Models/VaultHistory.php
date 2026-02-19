<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaultHistory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'vault_asset_id',
        'batch_id',
        'score',
        'individual_score',
        'sync_score',
        'linked_history_id',
        'status',
        'scanned_type',
        'metadata',
        'scanned_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'score' => 'float',
        'scanned_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(VaultAsset::class, 'vault_asset_id');
    }
}
