<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'audit_history';

    protected $fillable = [
        'vault_asset_id',
        'score',
        'audit_type',
        'status',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'score' => 'integer',
        'created_at' => 'datetime',
    ];

    public function vaultAsset(): BelongsTo
    {
        return $this->belongsTo(VaultAsset::class);
    }
}
