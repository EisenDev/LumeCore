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
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->jsonb('synced_assets')->nullable()->comment('Ids of linked assets {web: id, repo: id}');
            
            // Real Data Columns for Marketplace
            $table->string('website_url')->nullable();
            $table->string('repository_url')->nullable();
            
            // Snapshots for quick read
            $table->jsonb('website_metadata')->nullable();
            $table->jsonb('repository_metadata')->nullable();
            $table->jsonb('document_metadata')->nullable();
            $table->jsonb('synced_metadata')->nullable()->comment('Snapshot of comparison results');
            
            $table->decimal('sync_score', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->dropColumn([
                'synced_assets', 
                'website_url', 
                'repository_url', 
                'website_metadata', 
                'repository_metadata', 
                'document_metadata', 
                'synced_metadata', 
                'sync_score'
            ]);
        });
    }
};
