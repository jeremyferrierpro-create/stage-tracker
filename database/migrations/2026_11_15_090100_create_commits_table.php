<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commit importé depuis un dépôt (T-044, MLD §9). INV-05 : unicité
 * (repository_id, sha) — l'import est idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id');
            $table->foreignUuid('workspace_id');
            $table->char('sha', 40);
            $table->string('author_name', 160)->nullable();
            $table->string('author_email', 190)->nullable();
            $table->string('github_author', 120)->nullable();
            $table->timestampTz('committed_at', 3);
            $table->text('message');
            $table->string('branch', 200)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestampTz('imported_at', 3);
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);
            $table->unique(['repository_id', 'sha']);

            $table->foreign(['repository_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('repositories')
                ->cascadeOnDelete();

            $table->index(['workspace_id', 'committed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commits');
    }
};
