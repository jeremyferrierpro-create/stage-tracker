<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fichiers modifiés par un commit (T-045, MLD §9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('commit_id');
            $table->foreignUuid('workspace_id');
            $table->string('path', 500);
            $table->string('change_type', 20);
            $table->integer('additions')->nullable();
            $table->integer('deletions')->nullable();

            $table->unique(['commit_id', 'path']);

            $table->foreign(['commit_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('commits')
                ->cascadeOnDelete();

            $table->index(['workspace_id', 'path']);
        });

        DB::statement(
            "ALTER TABLE commit_files
             ADD CONSTRAINT commit_files_change_type_check
             CHECK (change_type IN ('added', 'modified', 'removed', 'renamed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('commit_files');
    }
};
