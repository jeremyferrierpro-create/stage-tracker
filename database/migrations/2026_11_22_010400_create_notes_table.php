<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pense-bête : notes personnelles ou partagées dans l'espace, avec
 * échéance, rappel et rattachement optionnel à un stage / projet (REQ-F-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('scope', 20)->default('personal');
            $table->string('status', 20)->default('todo');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('remind_at')->nullable();
            $table->timestampTz('reminded_at')->nullable();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('internship_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('done_at')->nullable();
            $table->timestampsTz();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'user_id']);
        });

        DB::statement(
            "ALTER TABLE notes
             ADD CONSTRAINT notes_scope_check
             CHECK (scope IN ('personal', 'workspace'))"
        );
        DB::statement(
            "ALTER TABLE notes
             ADD CONSTRAINT notes_status_check
             CHECK (status IN ('todo', 'done'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
