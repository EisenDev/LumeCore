<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Exception $e) {
            // Extension might not be installed on the server (e.g. Windows Postgres).
            // Proceed without it; embeddings features will be disabled.
            echo "Warning: pgvector extension could not be enabled. Vector search checks will be skipped.\n";
        }
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
