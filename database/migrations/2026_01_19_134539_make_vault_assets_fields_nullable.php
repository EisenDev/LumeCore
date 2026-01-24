<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes file_path, file_size, and mime_type nullable for Project/Website scans
     * that don't have an initial physical file.
     */
    public function up(): void
    {
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->string('file_path')->nullable()->change();
            $table->bigInteger('file_size')->nullable()->change();
            $table->string('mime_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_assets', function (Blueprint $table) {
            $table->string('file_path')->nullable(false)->change();
            $table->bigInteger('file_size')->nullable(false)->change();
            $table->string('mime_type')->nullable(false)->change();
        });
    }
};
