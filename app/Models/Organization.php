<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'owner_id',
    ];

    /**
     * Get the owner of the organization.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the members of the organization.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'email', 'name', 'invited_at'])
            ->withTimestamps();
    }
}
