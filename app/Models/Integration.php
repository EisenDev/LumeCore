<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'platform',
        'name',
        'description',
        'status',
        'credentials',
        'meta',
        'added_by',
        'connected_at',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Encrypt/decrypt credentials column using AES-256-CBC automatically
        'credentials'   => 'encrypted',
        'meta'          => 'array',
        'connected_at'  => 'datetime',
        'last_synced_at'=> 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Credentials MUST never be exposed to the frontend.
     *
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The user that owns this integration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All logs associated with this integration.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope to only return connected integrations.
     */
    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns a human-readable "last synced" string relative to now.
     */
    public function lastSyncForHumans(): string
    {
        return $this->last_synced_at
            ? $this->last_synced_at->diffForHumans()
            : 'Never';
    }

    /**
     * Maps the status to a Tailwind color class for the frontend.
     */
    public function statusColor(): string
    {
        return match ($this->status) {
            'connected'    => 'text-emerald-400',
            'warning'      => 'text-amber-400',
            'error'        => 'text-rose-500',
            default        => 'text-slate-500',
        };
    }
}
