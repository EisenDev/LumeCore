<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('asset_id');
            $table->text('content'); // The raw text chunk
            $table->timestamps();
            
            $table->foreign('asset_id')->references('id')->on('vault_assets')->onDelete('cascade');
            $table->index('asset_id');
        });
        
        // Add vector column using raw SQL (pgvector extension)
        DB::statement('ALTER TABLE asset_embeddings ADD COLUMN embedding vector(1536)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_embeddings');
    }
};
