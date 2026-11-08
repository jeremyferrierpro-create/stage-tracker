<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine Organisations — organisations d'accueil et organismes de formation
     * (MLD §3, FR-3.1).
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('type', 20);
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            // Patron d'isolation (MLD §1) : clé candidate pour les FK composites.
            $table->unique(['id', 'workspace_id']);
            $table->index(['workspace_id', 'archived_at']);
        });

        DB::statement(
            'ALTER TABLE organizations ADD CONSTRAINT ck_organizations_type '
            ."CHECK (type IN ('company', 'association', 'training_center', 'partner'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
