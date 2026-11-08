<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine Identité — invitations à rejoindre un workspace (MLD §2, APPFLOW §6.1).
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('email', 190);
            $table->string('role', 20);
            $table->string('token_hash', 255)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['workspace_id', 'email']);
        });

        DB::statement(
            'ALTER TABLE invitations ADD CONSTRAINT ck_invitations_role '
            ."CHECK (role IN ('owner', 'admin', 'trainee', 'supervisor', 'tutor', 'viewer'))"
        );

        // Une seule invitation en attente par workspace et par adresse (MLD §2).
        DB::statement(
            'CREATE UNIQUE INDEX invitations_pending_unique '
            .'ON invitations (workspace_id, email) WHERE accepted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
