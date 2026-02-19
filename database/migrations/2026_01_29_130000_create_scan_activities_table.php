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
        Schema::create('scan_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'document', 'website', 'repository', 'sync'
            $table->string('urls_and_sync')->nullable(); // Display name or URL
            $table->string('display_name')->nullable(); // Redundant? Maybe for cleaner UI usage

            // Links to actual Assets (so we can still open modals)
            $table->foreignUuid('primary_asset_id')->nullable()->constrained('vault_assets')->onDelete('set null');
            $table->foreignUuid('secondary_asset_id')->nullable()->constrained('vault_assets')->onDelete('set null'); // For sync (web + repo)

            // Statuses
            $table->string('sync_status')->nullable(); // verified, flagged, action_required
            $table->string('docu_and_urls_status')->nullable(); // verified, flagged, etc

            // Scores
            $table->decimal('sync_confidence_score', 5, 2)->nullable();
            $table->decimal('individual_score', 5, 2)->nullable();

            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_activities');
    }
};
