<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commentaires d'activité (T-050, MLD §12). Auteur et date immuables
 * (INV-12) : pas de colonne updated_at, aucun endpoint d'édition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id');
            $table->foreignUuid('workspace_id');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('visibility', 10)->default('stage');
            $table->timestampTz('created_at', 3)->useCurrent();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities');

            $table->index(['workspace_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE comments
             ADD CONSTRAINT comments_visibility_check
             CHECK (visibility IN ('private', 'stage', 'shared'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
