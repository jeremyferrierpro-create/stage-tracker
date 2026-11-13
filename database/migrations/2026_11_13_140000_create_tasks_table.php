<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tâche Kanban (T-026, MLD §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->nullable();
            $table->foreignUuid('workspace_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('status', 20)->default('todo');
            $table->date('due_date')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities');

            $table->index(['workspace_id', 'status', 'position']);
        });

        DB::statement(
            "ALTER TABLE tasks
             ADD CONSTRAINT tasks_priority_check
             CHECK (priority IN ('low', 'medium', 'high', 'critical'))"
        );

        DB::statement(
            "ALTER TABLE tasks
             ADD CONSTRAINT tasks_status_check
             CHECK (status IN ('todo', 'in_progress', 'to_review', 'done', 'blocked'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
