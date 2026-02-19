<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAudit extends Model
{
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'raw_results' => 'array',
        'specific_metadata' => 'array', // New field
        'analyzed_at' => 'datetime',
    ];

    public function findings()
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function vaultAsset()
    {
        return $this->belongsTo(VaultAsset::class);
    }
}
