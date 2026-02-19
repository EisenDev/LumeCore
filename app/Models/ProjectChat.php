<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChat extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'vault_asset_id',
        'conversation_id', // New field
        'message',
        'role',
        'mode',
    ];

    /**
     * Get the user that owns the chat message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vault asset that this chat is about.
     */
    public function vaultAsset(): BelongsTo
    {
        return $this->belongsTo(VaultAsset::class);
    }
}
