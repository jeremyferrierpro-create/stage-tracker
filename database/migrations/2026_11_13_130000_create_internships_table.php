<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage (T-023, MLD §6). Rattaché à un workspace, à un stagiaire, à une
 * organisation d'accueil et à une version de référentiel publiée.
 * FK composites : host_organization et training_program doivent appartenir
 * au même workspace (INV-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('trainee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('host_organization_id');
            $table->foreignUuid('training_program_id')->nullable();
            $table->foreignUuid('framework_version_id')->constrained('framework_versions');
            $table->string('title', 160);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('objectives')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['host_organization_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('organizations');

            $table->foreign(['training_program_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('training_programs');
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
