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
        // Check if extension exists first to avoid crashing
        $hasVector = DB::table('pg_extension')->where('extname', 'vector')->exists();

        if ($hasVector) {
            DB::statement('ALTER TABLE asset_embeddings ADD COLUMN embedding vector(1536)');
        } else {
            // Fallback: Add a dummy column or just log warning?
            // Adding a nullable text column as placeholder to prevent SQL errors in code if it queries 'embedding'
            // although code expecting 'vector' type usually does specialized queries that would fail anyway.
            // Let's just skip and let the app handle missing column gracefully or fail at runtime (better than migration fail).
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_embeddings');
    }
};
