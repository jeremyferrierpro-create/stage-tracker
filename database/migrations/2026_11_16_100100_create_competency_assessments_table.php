<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Évaluations nominatives de compétences (T-061, MLD §11). L'évaluateur
 * (assessor_id), la date (assessed_at) et l'appréciation sont conservés —
 * provenance traçable, aucune écriture automatique (AC-11.2, INV-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_competency_id');
            $table->foreignId('assessor_id')->constrained('users');
            $table->text('appreciation');
            $table->string('status', 20);
            $table->timestampTz('assessed_at', 3)->useCurrent();

            $table->foreign('activity_competency_id')
                ->references('id')
                ->on('activity_competencies')
                ->cascadeOnDelete();

            $table->index(['activity_competency_id', 'assessed_at']);
        });

        DB::statement(
            "ALTER TABLE competency_assessments
             ADD CONSTRAINT competency_assessments_status_check
             CHECK (status IN ('pending', 'validated', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_assessments');
    }
};
