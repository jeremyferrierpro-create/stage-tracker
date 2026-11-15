<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preuve (T-041, MLD §10). Une preuve est soit un fichier stocké
 * (storage_key), soit un lien externe (external_url) — CHECK au moins l'un.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->text('demonstrated');
            $table->string('storage_key', 500)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('visibility', 10)->default('private');
            $table->timestampTz('captured_at', 3)->nullable();
            $table->timestampTz('archived_at', 3)->nullable();
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);

            $table->index(['workspace_id', 'visibility']);
        });

        DB::statement(
            "ALTER TABLE evidences
             ADD CONSTRAINT evidences_type_check
             CHECK (type IN ('screenshot','mockup','uml','mcd','mld','test_report','lighthouse','accessibility','technical_doc','commit','pull_request','external_link'))"
        );

        DB::statement(
            "ALTER TABLE evidences
             ADD CONSTRAINT evidences_visibility_check
             CHECK (visibility IN ('private', 'stage', 'shared'))"
        );

        DB::statement(
            'ALTER TABLE evidences
             ADD CONSTRAINT evidences_storage_or_url_check
             CHECK (storage_key IS NOT NULL OR external_url IS NOT NULL)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
