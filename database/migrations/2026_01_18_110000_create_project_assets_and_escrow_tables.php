<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add escrow fields to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('escrow_status')->nullable()->after('metadata'); // pending, locked, released, refunded
            $table->timestamp('release_at')->nullable()->after('escrow_status');
            $table->timestamp('released_at')->nullable()->after('release_at');
        });

        // Create project_assets table for website/codebase listings
        Schema::create('project_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('vault_asset_id')->nullable()->constrained('vault_assets')->onDelete('set null');
            
            // Project Details
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('github_repo_url')->nullable();
            
            // Verification
            $table->string('verification_uuid')->nullable();
            $table->boolean('website_verified')->default(false);
            $table->boolean('github_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            
            // Health Check Data
            $table->json('health_data')->nullable(); // domain_expiry, ssl_status, uptime, etc.
            $table->json('github_data')->nullable(); // stars, last_commit, languages, etc.
            
            // Credentials (encrypted)
            $table->text('github_token_encrypted')->nullable();
            $table->text('cloudflare_key_encrypted')->nullable();
            $table->string('cloudflare_email')->nullable();
            
            // Listing Status
            $table->enum('status', ['draft', 'pending_verification', 'verified', 'listed', 'sold', 'transferred'])->default('draft');
            $table->decimal('asking_price', 15, 2)->nullable();
            $table->decimal('monthly_revenue', 15, 2)->nullable();
            $table->integer('monthly_visitors')->nullable();
            
            // AI Audit Data
            $table->json('audit_data')->nullable();
            $table->integer('lume_score')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('verification_uuid');
        });

        // Create escrow_transactions for the 7-day atomic escrow
        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_asset_id')->constrained('project_assets')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            
            $table->enum('status', ['pending_payment', 'locked', 'transferring', 'completed', 'refunded', 'disputed'])->default('pending_payment');
            
            // Escrow Timeline
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('release_at')->nullable(); // When funds can be released (7 days after lock)
            $table->timestamp('transfer_started_at')->nullable();
            $table->timestamp('transfer_completed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            
            // Transfer Details
            $table->string('buyer_github_username')->nullable();
            $table->json('transfer_log')->nullable();
            
            // Payment Gateway
            $table->string('payment_intent_id')->nullable();
            $table->string('payment_method')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'release_at']);
            $table->index('buyer_id');
            $table->index('seller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_transactions');
        Schema::dropIfExists('project_assets');
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['escrow_status', 'release_at', 'released_at']);
        });
    }
};
