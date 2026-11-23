<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planning prévisionnel jour par jour (prévu), comparé aux daily_logs (réel).
 * Importé depuis un document markdown via `stage:import-planning`.
 * Ne modifie jamais les daily_logs : donnée séparée en lecture seule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('internship_id');
            $table->foreignUuid('workspace_id');
            $table->foreignUuid('project_id')->nullable();
            $table->date('plan_date');
            $table->string('slot', 10);
            $table->string('title', 200);
            $table->smallInteger('planned_minutes');
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['internship_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('internships');

            $table->foreign(['project_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('projects');

            $table->index(['internship_id', 'plan_date']);
            $table->index(['workspace_id', 'plan_date']);
        });

        DB::statement(
            "ALTER TABLE plan_entries
             ADD CONSTRAINT plan_entries_slot_check
             CHECK (slot IN ('morning', 'afternoon'))"
        );

        DB::statement(
            'ALTER TABLE plan_entries
             ADD CONSTRAINT plan_entries_planned_minutes_check
             CHECK (planned_minutes > 0 AND planned_minutes <= 600)'
        );

        DB::statement(
            'ALTER TABLE plan_entries
             ADD CONSTRAINT plan_entries_position_check
             CHECK (position >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_entries');
    }
};
