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
            $table->string('fingerprint_hash')->nullable()->index()->after('metadata');
        });

        Schema::table('project_assets', function (Blueprint $table) {
            $table->string('fingerprint_hash')->nullable()->index()->after('github_repo_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->dropColumn('fingerprint_hash');
        });

        Schema::table('project_assets', function (Blueprint $table) {
            $table->dropColumn('fingerprint_hash');
        });
    }
};
