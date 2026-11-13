<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscriptions à un programme de formation (T-020, MLD §4).
 * Un utilisateur est inscrit une seule fois à un programme (UNIQUE) ; la
 * ligne est rattachée au workspace du programme via FK composite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('training_program_id');
            $table->foreignUuid('workspace_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'training_program_id']);

            $table->foreign(['training_program_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('training_programs');
        });

        DB::statement(
            "ALTER TABLE training_enrollments
             ADD CONSTRAINT training_enrollments_status_check
             CHECK (status IN ('active', 'completed', 'withdrawn'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
    }
};
