<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lien many-to-many entre une entrée de planning prévisionnel
 * (plan_entries) et les compétences du workspace. Permet de savoir
 * quelles compétences une journée planifiée vise à mobiliser ;
 * les compétences réelles mobilisées restent portées par
 * activity_competencies sur les activités liées.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nécessaire pour la clé étrangère composite (competency_id, workspace_id).
        DB::statement(
            'ALTER TABLE competencies
             ADD CONSTRAINT competencies_id_workspace_id_unique
             UNIQUE (id, workspace_id)'
        );

        Schema::create('plan_entry_competencies', function (Blueprint $table): void {
            $table->foreignUuid('plan_entry_id');
            $table->foreignUuid('competency_id');
            $table->foreignUuid('workspace_id');
            $table->timestamps();

            $table->unique(['plan_entry_id', 'competency_id']);

            $table->foreign(['plan_entry_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('plan_entries')
                ->cascadeOnDelete();

            $table->foreign(['competency_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('competencies')
                ->cascadeOnDelete();

            $table->index(['workspace_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_entry_competencies');
        DB::statement('ALTER TABLE competencies DROP CONSTRAINT IF EXISTS competencies_id_workspace_id_unique');
    }
};
