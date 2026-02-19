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
        Schema::table('security_audits', function (Blueprint $table) {
            $table->json('specific_metadata')->nullable()->after('audit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_audits', function (Blueprint $table) {
            $table->dropColumn('specific_metadata');
        });
    }
};
