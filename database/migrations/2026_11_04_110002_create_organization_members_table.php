<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine Organisations — rattachement des utilisateurs aux organisations
     * (MLD §3, FR-3.3).
     */
    public function up(): void
    {
        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->uuid('workspace_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestampsTz();

            // FK composite : impossible de rattacher un membre à une organisation
            // d'un autre workspace (INV-11).
            $table->foreign(['organization_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('organizations')
                ->cascadeOnDelete();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
