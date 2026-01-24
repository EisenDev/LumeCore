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
        Schema::create('audit_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vault_asset_id')->constrained('vault_assets')->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->string('status');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_history');
    }
};
