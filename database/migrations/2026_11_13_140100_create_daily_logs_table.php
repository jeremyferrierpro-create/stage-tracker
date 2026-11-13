<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal quotidien (T-027, MLD §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('internship_id');
            $table->foreignUuid('workspace_id');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->smallInteger('duration_minutes')->nullable();
            $table->text('objectives')->nullable();
            $table->text('work_done')->nullable();
            $table->text('difficulties')->nullable();
            $table->text('solutions')->nullable();
            $table->text('results')->nullable();
            $table->text('remaining')->nullable();
            $table->text('private_notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('closed_at', 3)->nullable();
            $table->timestamps();

            $table->unique(['internship_id', 'author_id', 'work_date']);

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['internship_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('internships');

            $table->index(['internship_id', 'work_date']);
        });

        DB::statement(
            "ALTER TABLE daily_logs
             ADD CONSTRAINT daily_logs_status_check
             CHECK (status IN ('draft', 'closed'))"
        );

        DB::statement(
            'ALTER TABLE daily_logs
             ADD CONSTRAINT daily_logs_duration_check
             CHECK (duration_minutes IS NULL OR duration_minutes >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_logs');
    }
};
