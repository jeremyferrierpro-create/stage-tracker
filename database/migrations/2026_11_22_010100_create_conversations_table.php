<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations de messagerie interne (REQ-F-003) : direct, groupe,
 * ou fil rattaché à un projet / stage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title')->nullable();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('internship_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direct_key', 80)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->index(['workspace_id', 'type']);
        });

        DB::statement(
            "ALTER TABLE conversations
             ADD CONSTRAINT conversations_type_check
             CHECK (type IN ('direct', 'group', 'project', 'internship'))"
        );

        // Un fil direct par paire d'utilisateurs, un fil par projet et par stage.
        DB::statement(
            'CREATE UNIQUE INDEX conversations_direct_key_unique
             ON conversations (workspace_id, direct_key)
             WHERE direct_key IS NOT NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX conversations_project_unique
             ON conversations (workspace_id, project_id)
             WHERE project_id IS NOT NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX conversations_internship_unique
             ON conversations (workspace_id, internship_id)
             WHERE internship_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
