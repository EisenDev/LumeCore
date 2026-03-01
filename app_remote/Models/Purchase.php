<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vault_asset_id',
        'price',
        'fee',
        'transaction_id',
        'status',
        'unlocked_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'price' => 'decimal:2',
        'fee' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(VaultAsset::class, 'vault_asset_id');
    }
}
