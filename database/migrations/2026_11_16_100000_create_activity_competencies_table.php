<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Associations activité ↔ compétence (T-060, MLD §11). La justification
 * est obligatoire ; aucun horodatage créé/modifié (le statut est porté par
 * documented_at et les évaluations, INV-09 : jamais de transition
 * automatique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_competencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id');
            $table->foreignUuid('workspace_id');
            $table->foreignUuid('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->text('justification');
            $table->string('status', 20)->default('mobilized');
            $table->timestampTz('documented_at', 3)->nullable();

            $table->unique(['id', 'workspace_id']);
            $table->unique(['activity_id', 'competency_id']);

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities');

            $table->index(['workspace_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE activity_competencies
             ADD CONSTRAINT activity_competencies_status_check
             CHECK (status IN ('mobilized', 'documented', 'evaluated'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_competencies');
    }
};
