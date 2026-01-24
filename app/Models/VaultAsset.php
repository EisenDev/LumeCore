<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaultAsset extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'status',
        'metadata',
        'full_audit_report',
        'radar_data',
        'suggested_value',
        'is_for_sale',
        'price',
        'sale_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'metadata' => 'array',
            'radar_data' => 'array',
        ];
    }

    /**
     * Get the user that owns the vault asset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditHistory(): HasMany
    {
        return $this->hasMany(AuditHistory::class)->orderBy('created_at', 'desc');
    }
}
