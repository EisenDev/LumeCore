<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MarketplaceListing extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'vault_asset_id',
        'user_id',
        'price',
        'ai_score_snapshot',
        'status',
    ];

    public function asset()
    {
        return $this->belongsTo(VaultAsset::class, 'vault_asset_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
