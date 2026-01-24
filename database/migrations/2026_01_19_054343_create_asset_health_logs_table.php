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
        Schema::create('asset_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_asset_id')->constrained()->cascadeOnDelete();
            $table->string('check_type'); // 'http', 'domain'
            $table->string('status'); // 'ok', 'failed'
            $table->integer('response_time_ms')->nullable();
            $table->string('message')->nullable(); // Error description
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_health_logs');
    }
};
