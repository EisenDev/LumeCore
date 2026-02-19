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
            if (!Schema::hasColumn('vault_assets', 'batch_id')) {
                $table->string('batch_id')->nullable()->after('metadata')->index();
            }
        });

        Schema::table('scan_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('scan_activities', 'batch_id')) {
                $table->string('batch_id')->nullable()->after('vectors')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });

        Schema::table('scan_activities', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
