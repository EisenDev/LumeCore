<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class ProjectAsset extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'vault_asset_id',
        'name',
        'description',
        'website_url',
        'github_repo_url',
        'verification_uuid',
        'website_verified',
        'github_verified',
        'verified_at',
        'health_data',
        'github_data',
        'github_token_encrypted',
        'cloudflare_key_encrypted',
        'cloudflare_email',
        'status',
        'asking_price',
        'monthly_revenue',
        'monthly_visitors',
        'audit_data',
        'lume_score',
    ];

    protected $casts = [
        'website_verified' => 'boolean',
        'github_verified' => 'boolean',
        'verified_at' => 'datetime',
        'health_data' => 'array',
        'github_data' => 'array',
        'audit_data' => 'array',
        'asking_price' => 'decimal:2',
        'monthly_revenue' => 'decimal:2',
    ];

    protected $hidden = [
        'github_token_encrypted',
        'cloudflare_key_encrypted',
    ];

    /**
     * Get the user that owns this project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the associated vault asset if any.
     */
    public function vaultAsset(): BelongsTo
    {
        return $this->belongsTo(VaultAsset::class);
    }

    /**
     * Get escrow transactions for this project.
     */
    public function escrowTransactions(): HasMany
    {
        return $this->hasMany(EscrowTransaction::class);
    }

    /**
     * Set the GitHub token (encrypted).
     */
    public function setGithubToken(?string $token): void
    {
        $this->github_token_encrypted = $token ? Crypt::encryptString($token) : null;
    }

    /**
     * Get the GitHub token (decrypted).
     */
    public function getGithubToken(): ?string
    {
        if (!$this->github_token_encrypted) {
            return null;
        }
        
        try {
            return Crypt::decryptString($this->github_token_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the Cloudflare key (encrypted).
     */
    public function setCloudflareKey(?string $key): void
    {
        $this->cloudflare_key_encrypted = $key ? Crypt::encryptString($key) : null;
    }

    /**
     * Get the Cloudflare key (decrypted).
     */
    public function getCloudflareKey(): ?string
    {
        if (!$this->cloudflare_key_encrypted) {
            return null;
        }
        
        try {
            return Crypt::decryptString($this->cloudflare_key_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if project is fully verified.
     */
    public function isFullyVerified(): bool
    {
        $websiteOk = !$this->website_url || $this->website_verified;
        $githubOk = !$this->github_repo_url || $this->github_verified;
        
        return $websiteOk && $githubOk;
    }

    /**
     * Check if project can be listed for sale.
     */
    public function canBeListed(): bool
    {
        return $this->isFullyVerified() && 
               $this->lume_score >= 70 &&
               $this->asking_price > 0;
    }

    /**
     * Parse GitHub URL to get owner and repo name.
     * @return array|null ['owner' => string, 'repo' => string]
     */
    public function getRepoParts(): ?array
    {
        if (!$this->github_repo_url) {
            return null;
        }

        preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $this->github_repo_url, $matches);
        
        if (count($matches) < 3) {
            return null;
        }

        return [
            'owner' => $matches[1],
            'repo' => rtrim($matches[2], '.git')
        ];
    }
}
