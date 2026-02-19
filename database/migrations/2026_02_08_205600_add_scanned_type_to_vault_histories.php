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
            if (!Schema::hasColumn('vault_histories', 'scanned_type')) {
                $table->string('scanned_type')->nullable()->after('status')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_histories', function (Blueprint $table) {
            $table->dropColumn('scanned_type');
        });
    }
};
