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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('vault_asset_id')->constrained('vault_assets')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->decimal('fee', 10, 2);
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('completed'); // completed, refunded
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
