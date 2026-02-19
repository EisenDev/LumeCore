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
            if (!Schema::hasColumn('vault_assets', 'suggested_value')) {
                $table->decimal('suggested_value', 10, 2)->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('vault_assets', 'is_for_sale')) {
                $table->boolean('is_for_sale')->default(false)->index();
            }
            if (!Schema::hasColumn('vault_assets', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->index();
            }
            if (!Schema::hasColumn('vault_assets', 'sale_count')) {
                $table->integer('sale_count')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->dropColumn(['is_for_sale', 'price', 'sale_count']);
        });
    }
};
