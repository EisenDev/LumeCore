<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetEmbedding extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'asset_id',
        'content',
        'embedding',
    ];

    /**
     * Get the vault asset that owns this embedding.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(VaultAsset::class, 'asset_id');
    }

    /**
     * Set the embedding attribute.
     * Converts array to pgvector format.
     */
    public function setEmbeddingAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['embedding'] = '[' . implode(',', $value) . ']';
        } else {
            $this->attributes['embedding'] = $value;
        }
    }

    /**
     * Get the embedding attribute.
     * Converts pgvector format to array.
     */
    public function getEmbeddingAttribute($value)
    {
        if (is_string($value)) {
            $value = trim($value, '[]');
            return array_map('floatval', explode(',', $value));
        }
        return $value;
    }
}
