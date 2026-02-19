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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_type')->default('developer'); // 'developer', 'agency'
            $table->string('status'); // 'active', 'canceled', 'expired', 'trialing'
            
            // Usage Tracking
            $table->integer('daily_individual_scans_used')->default(0);
            $table->integer('daily_sync_scans_used')->default(0);
            $table->integer('monthly_pentests_used')->default(0);
            $table->timestamp('last_daily_reset_at')->nullable();
            $table->timestamp('last_monthly_reset_at')->nullable();

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
