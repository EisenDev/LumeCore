<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'invoice_number',
        'plan_type',
        'amount',
        'status',
        'billing_date',
        'reference_id',
    ];

    protected $casts = [
        'billing_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
