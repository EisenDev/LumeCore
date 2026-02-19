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
        if (!Schema::hasColumn('project_chats', 'mode')) {
            Schema::table('project_chats', function (Blueprint $table) {
                $table->string('mode')->nullable()->after('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('project_chats', 'mode')) {
            Schema::table('project_chats', function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }
    }
};
