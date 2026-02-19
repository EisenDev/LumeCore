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
        if (Schema::hasColumn('vault_histories', 'linked_history_id')) {
            // Raw statement to force the type change with casting
            DB::statement('ALTER TABLE vault_histories ALTER COLUMN linked_history_id TYPE JSONB USING to_jsonb(linked_history_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_histories', function (Blueprint $table) {
             // Revert to UUID (This will fail if JSON has arrays, but safe for rollback of this specific step)
        });
        
        DB::statement('ALTER TABLE vault_histories ALTER COLUMN linked_history_id TYPE UUID USING (linked_history_id->>0)::uuid');
    }
};

