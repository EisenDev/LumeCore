<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditFinding extends Model
{
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'evidence' => 'array',
    ];

    public function securityAudit()
    {
        return $this->belongsTo(SecurityAudit::class);
    }
}
