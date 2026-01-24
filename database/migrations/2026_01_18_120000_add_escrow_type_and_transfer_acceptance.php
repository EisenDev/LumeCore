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
        // Add escrow_type to escrow_transactions
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->string('escrow_type')->default('asset_transfer')->after('status');
            $table->timestamp('transfer_accepted_at')->nullable()->after('transfer_completed_at');
            $table->boolean('buyer_accepted_transfer')->default(false)->after('buyer_github_username');
        });

        // Add escrow_type to project_assets for tracking
        Schema::table('project_assets', function (Blueprint $table) {
            $table->json('transfer_requirements')->nullable()->after('audit_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropColumn(['escrow_type', 'transfer_accepted_at', 'buyer_accepted_transfer']);
        });

        Schema::table('project_assets', function (Blueprint $table) {
            $table->dropColumn('transfer_requirements');
        });
    }
};
