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
        // Wallets table - One wallet per user
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            // Indexes for speed
            $table->index('balance');
            $table->index('currency');
        });

        // Transactions table - All money movements linked to wallets
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->uuid('asset_id')->nullable(); // Links to vault_assets for file-related transactions
            $table->string('type'); // 'credit' or 'debit'
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->string('reference_number')->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('wallet_id')
                ->references('id')
                ->on('wallets')
                ->cascadeOnDelete();

            $table->foreign('asset_id')
                ->references('id')
                ->on('vault_assets')
                ->nullOnDelete();

            // Indexes for speed
            $table->index('wallet_id');
            $table->index('asset_id');
            $table->index('type');
            $table->index('created_at');
            $table->index(['wallet_id', 'type']); // Composite index for filtering by type per wallet
            $table->index(['wallet_id', 'created_at']); // Composite index for transaction history
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
    }
};
