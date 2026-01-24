<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
             // Ignore if it doesn't exist (SQLite/MySQL might behave differently, but we target Postgres given the error log)
        }

        // Re-add the check constraint with new allowed values
        // Added: verified_private, action_required, failed_system, improvement_needed
        DB::statement("ALTER TABLE project_assets ADD CONSTRAINT project_assets_status_check CHECK (status::text IN ('draft', 'pending_verification', 'verified', 'listed', 'sold', 'transferred', 'verified_private', 'action_required', 'failed_system', 'improvement_needed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original check constraint
        try {
             DB::statement("ALTER TABLE project_assets DROP CONSTRAINT IF EXISTS project_assets_status_check");
             DB::statement("ALTER TABLE project_assets ADD CONSTRAINT project_assets_status_check CHECK (status::text IN ('draft', 'pending_verification', 'verified', 'listed', 'sold', 'transferred'))");
        } catch (\Exception $e) {
            // Best effort
        }
    }
};
