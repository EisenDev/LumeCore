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
        // Update audit_history score to decimal using Raw SQL (Postgres)
        // We use USING to ensure proper casting if needed, though int -> decimal is implicit.
        DB::statement("ALTER TABLE audit_history ALTER COLUMN score TYPE decimal(5, 2)");
        
        // Update project_assets lume_score to decimal
        DB::statement("ALTER TABLE project_assets ALTER COLUMN lume_score TYPE decimal(5, 2)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to integer (potentially lossy, so we floor it)
        DB::statement("ALTER TABLE audit_history ALTER COLUMN score TYPE integer");
        
        DB::statement("ALTER TABLE project_assets ALTER COLUMN lume_score TYPE integer");
    }
};
