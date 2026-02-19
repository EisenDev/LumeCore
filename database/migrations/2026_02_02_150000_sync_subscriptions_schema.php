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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Handle renaming plan_name to plan_type if necessary
            if (Schema::hasColumn('subscriptions', 'plan_name') && !Schema::hasColumn('subscriptions', 'plan_type')) {
                $table->renameColumn('plan_name', 'plan_type');
            } elseif (!Schema::hasColumn('subscriptions', 'plan_type')) {
                $table->string('plan_type')->default('developer')->after('user_id');
            }

            // Add usage tracking columns if they are missing
            if (!Schema::hasColumn('subscriptions', 'daily_individual_scans_used')) {
                $table->integer('daily_individual_scans_used')->default(0);
            }
            if (!Schema::hasColumn('subscriptions', 'daily_sync_scans_used')) {
                $table->integer('daily_sync_scans_used')->default(0);
            }
            if (!Schema::hasColumn('subscriptions', 'monthly_pentests_used')) {
                $table->integer('monthly_pentests_used')->default(0);
            }
            if (!Schema::hasColumn('subscriptions', 'last_daily_reset_at')) {
                $table->timestamp('last_daily_reset_at')->nullable();
            }
            if (!Schema::hasColumn('subscriptions', 'last_monthly_reset_at')) {
                $table->timestamp('last_monthly_reset_at')->nullable();
            }
            
            // Ensure status column exists (though it should)
            if (!Schema::hasColumn('subscriptions', 'status')) {
                $table->string('status')->default('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No broad down migration for a fix script to avoid data loss
    }
};
