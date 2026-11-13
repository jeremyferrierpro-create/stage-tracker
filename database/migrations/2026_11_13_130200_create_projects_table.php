<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projet rattaché à un stage (T-024, MLD §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('internship_id');
            $table->foreignUuid('workspace_id');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['internship_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('internships');
        });

        DB::statement(
            "ALTER TABLE projects
             ADD CONSTRAINT projects_status_check
             CHECK (status IN ('active', 'completed', 'archived'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
