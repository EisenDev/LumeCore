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
        // Drop the table first to reset the schema (Fixing ID type and missing columns)
        Schema::dropIfExists('vault_histories');

        Schema::create('vault_histories', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Model uses HasUuids
            $table->uuid('vault_asset_id')->index();
            $table->string('batch_id')->nullable()->index();
            $table->string('scanned_type')->nullable()->index(); // website, repository, sync
            
            $table->string('status')->default('pending')->index();
            
            // Scores (Using double/float to support decimals like 83.33)
            $table->float('score')->nullable();
            $table->float('individual_score')->nullable();
            $table->float('sync_score')->nullable();
            
            // Linking
            $table->uuid('linked_history_id')->nullable()->index();
            
            // JSON Metadata
            $table->jsonb('metadata')->nullable();
            
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_histories');
    }
};
