<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage (T-023, MLD §6). Rattaché à un workspace et à un stagiaire.
 * Simplification 1.6.0 (décision utilisateur) : l'entreprise d'accueil est
 * un champ texte `company_name`, le programme de formation et la version de
 * référentiel sont supprimés — les compétences sont définies directement
 * au niveau du workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('trainee_id')->constrained('users')->cascadeOnDelete();
            $table->string('company_name', 200);
            $table->string('title', 160);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('objectives')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);
        });

        DB::statement(
            "ALTER TABLE internships
             ADD CONSTRAINT internships_status_check
             CHECK (status IN ('draft', 'active', 'completed', 'archived'))"
        );

        DB::statement(
            'ALTER TABLE internships
             ADD CONSTRAINT internships_dates_check
             CHECK (end_date >= start_date)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};
