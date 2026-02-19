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
        Schema::create('security_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vault_asset_id');
            $table->decimal('security_score', 5, 2)->default(0);
            $table->decimal('qa_score', 5, 2)->default(0);
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->text('summary')->nullable();
            $table->json('raw_results')->nullable();
            $table->string('audit_type')->default('pentest'); // pentest, rescan
            $table->timestamps();

            $table->foreign('vault_asset_id')->references('id')->on('vault_assets')->onDelete('cascade');
        });

        Schema::create('audit_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('security_audit_id');
            $table->string('severity'); // critical, high, medium, low, info
            $table->string('type'); // Changed from 'title' to match code
            $table->string('location'); // Changed from 'category' to match code
            $table->text('description')->nullable();
            $table->text('remediation')->nullable();
            $table->json('evidence')->nullable(); // proof snippets
            $table->string('status')->default('OPEN'); // Changed from 'is_fixed' to match code (OPEN, FIXED, IGNORED)
            $table->timestamps();

            $table->foreign('security_audit_id')->references('id')->on('security_audits')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('security_audits');
    }
};
