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
        Schema::create('vault_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vault_asset_id')->constrained('vault_assets')->onDelete('cascade');
            $table->string('batch_id')->nullable()->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('status')->default('processing');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps(); // created_at = archived_at
            
            $table->index(['vault_asset_id', 'batch_id']);
            $table->index('created_at');
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
