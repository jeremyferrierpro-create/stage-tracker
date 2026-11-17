<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compétences du workspace (T-021 simplifié, décision utilisateur 1.6.0).
 * Liste simple définie directement par les administrateurs de l'espace :
 * plus de référentiel versionné, de bloc ni de critère. Chaque compétence
 * est rattachable à une activité (T-060) et évaluable par le tuteur
 * ou maître de stage (T-061).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'code']);
        });

        DB::statement(
            'ALTER TABLE competencies
             ADD CONSTRAINT competencies_title_check
             CHECK (length(title) > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('competencies');
    }
};
