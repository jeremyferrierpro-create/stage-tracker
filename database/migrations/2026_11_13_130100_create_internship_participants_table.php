<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Participants d'un stage (T-023, MLD §6). Un utilisateur peut avoir
 * plusieurs rôles dans le même stage (UNIQUE (internship_id, user_id, role)).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('internship_id');
            $table->foreignUuid('workspace_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->jsonb('permissions')->default('{}');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign(['internship_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('internships');

            $table->unique(['internship_id', 'user_id', 'role']);
        });

        DB::statement(
            "ALTER TABLE internship_participants
             ADD CONSTRAINT internship_participants_role_check
             CHECK (role IN ('trainee', 'supervisor', 'tutor'))"
        );

        DB::statement(
            "ALTER TABLE internship_participants
             ADD CONSTRAINT internship_participants_status_check
             CHECK (status IN ('active', 'removed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_participants');
    }
};
