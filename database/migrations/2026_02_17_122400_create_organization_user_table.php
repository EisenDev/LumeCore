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
        Schema::create('organization_user', function (Blueprint $row) {
            $row->id();
            $row->foreignId('organization_id')->constrained()->onDelete('cascade');
            $row->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $row->string('email');
            $row->string('name')->nullable();
            $row->enum('role', [
                'document_auditor', 
                'project_auditor', 
                'full_auditor'
            ])->default('document_auditor');
            $row->timestamp('invited_at')->nullable();
            $row->timestamps();
            
            $row->unique(['organization_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
