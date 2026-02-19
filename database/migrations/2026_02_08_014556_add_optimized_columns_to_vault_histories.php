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
        Schema::table('vault_histories', function (Blueprint $table) {
            // TITAN V6.5: Optimized High-Performance History Columns
            if (!Schema::hasColumn('vault_histories', 'individual_score')) {
                $table->decimal('individual_score', 5, 2)->nullable()->after('score')->comment('Intrinsic Health Score (Web/Repo)');
            }
            if (!Schema::hasColumn('vault_histories', 'sync_score')) {
                $table->decimal('sync_score', 5, 2)->nullable()->after('individual_score')->comment('Contextual Sync Strength');
            }
            if (!Schema::hasColumn('vault_histories', 'linked_history_id')) {
                $table->uuid('linked_history_id')->nullable()->after('metadata')->comment('Safety Link to Sibling History');
                $table->index('linked_history_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_histories', function (Blueprint $table) {
            $table->dropColumn(['individual_score', 'sync_score', 'linked_history_id']);
        });
    }
};
