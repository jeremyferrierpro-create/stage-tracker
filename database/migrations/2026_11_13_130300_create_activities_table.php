<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activité technique (T-025, MLD §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id');
            $table->foreignUuid('workspace_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->string('status', 20)->default('todo');
            $table->string('priority', 10)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('objectives')->nullable();
            $table->text('results')->nullable();
            $table->text('difficulties')->nullable();
            $table->text('solutions')->nullable();
            $table->timestampTz('archived_at', 3)->nullable();
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['project_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('projects');
        });

        DB::statement(
            "ALTER TABLE activities
             ADD CONSTRAINT activities_type_check
             CHECK (type IN ('feature', 'fix', 'study', 'design', 'research', 'maintenance', 'improvement'))"
        );

        DB::statement(
            "ALTER TABLE activities
             ADD CONSTRAINT activities_status_check
             CHECK (status IN ('todo', 'in_progress', 'to_review', 'done', 'blocked'))"
        );

        DB::statement(
            "ALTER TABLE activities
             ADD CONSTRAINT activities_priority_check
             CHECK (priority IN ('low', 'medium', 'high', 'critical'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
