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
            $table->decimal('suggested_value', 10, 2)->nullable()->after('metadata');
            $table->boolean('is_for_sale')->default(false)->after('suggested_value');
            $table->decimal('price', 15, 2)->nullable()->after('is_for_sale');
            $table->integer('sale_count')->default(0)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->dropColumn(['suggested_value', 'is_for_sale', 'price', 'sale_count']);
        });
    }
};
