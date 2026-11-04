<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine Identité — appartenances et rôles contextualisés (MLD §2, ADR-002).
     */
    public function up(): void
    {
        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20)->default('active');
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE workspace_members ADD CONSTRAINT ck_workspace_members_role '
            ."CHECK (role IN ('owner', 'admin', 'trainee', 'supervisor', 'tutor', 'viewer'))"
        );

        DB::statement(
            'ALTER TABLE workspace_members ADD CONSTRAINT ck_workspace_members_status '
            ."CHECK (status IN ('invited', 'active', 'suspended', 'removed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
    }
};
