<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityFinding extends Model
{
    protected $guarded = [];

    public function audit()
    {
        return $this->belongsTo(SecurityAudit::class, 'security_audit_id');
    }
}
