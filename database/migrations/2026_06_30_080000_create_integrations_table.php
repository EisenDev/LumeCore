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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Platform identifier (e.g. 'github', 'gitlab', 'slack', 'jira')
            $table->string('platform');

            // Display name and description
            $table->string('name');
            $table->string('description')->nullable();

            // Connection state: 'connected' | 'disconnected' | 'error' | 'warning'
            $table->string('status')->default('disconnected');

            // Encrypted sensitive configuration (API keys, tokens, webhook URLs)
            // Uses Laravel's built-in AES-256-CBC encryption via cast
            $table->text('credentials')->nullable();

            // Additional non-sensitive metadata (e.g. workspace ID, org name)
            $table->json('meta')->nullable();

            // Who connected the integration (user display name)
            $table->string('added_by')->nullable();

            // Timestamps for connection lifecycle
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // Ensure one integration platform per user (unique constraint)
            $table->unique(['user_id', 'platform']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
