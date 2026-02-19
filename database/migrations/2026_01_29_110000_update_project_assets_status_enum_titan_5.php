<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing check constraint
        try {
             DB::statement("ALTER TABLE project_assets DROP CONSTRAINT IF EXISTS project_assets_status_check");
        } catch (\Exception $e) {
             // Ignore if it doesn't exist
        }

        // Re-add the check constraint with ALL allowed values, including 'scanning' and 'processing'
        DB::statement("ALTER TABLE project_assets ADD CONSTRAINT project_assets_status_check CHECK (status::text IN ('draft', 'pending_verification', 'scanning', 'processing', 'pending_audit', 'verified', 'listed', 'sold', 'transferred', 'verified_private', 'action_required', 'failed_system', 'improvement_needed', 'flagged'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
             DB::statement("ALTER TABLE project_assets DROP CONSTRAINT IF EXISTS project_assets_status_check");
             // Revert to the list as of the previous migration
             DB::statement("ALTER TABLE project_assets ADD CONSTRAINT project_assets_status_check CHECK (status::text IN ('draft', 'pending_verification', 'verified', 'listed', 'sold', 'transferred', 'verified_private', 'action_required', 'failed_system', 'improvement_needed', 'flagged'))");
        } catch (\Exception $e) {
            // Best effort
        }
    }
};
