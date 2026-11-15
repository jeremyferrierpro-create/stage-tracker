<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dépôt Git associé à un projet (T-043, MLD §9).
 * Complément MLD : `token_encrypted` (jeton chiffré côté serveur, jamais
 * exposé au navigateur — README §11) ; masqué au sérialiseur du modèle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id');
            $table->foreignUuid('workspace_id');
            $table->string('provider', 20)->default('github');
            $table->string('external_id', 120);
            $table->string('name', 160);
            $table->string('repository_url', 500);
            $table->string('default_branch', 120)->nullable();
            $table->timestampTz('last_synced_at', 3)->nullable();
            $table->text('token_encrypted')->nullable();
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);
            $table->unique(['provider', 'external_id']);

            $table->foreign(['project_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('projects');

            $table->index(['workspace_id', 'provider']);
        });

        DB::statement(
            "ALTER TABLE repositories
             ADD CONSTRAINT repositories_provider_check
             CHECK (provider IN ('github', 'gitlab', 'other'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
