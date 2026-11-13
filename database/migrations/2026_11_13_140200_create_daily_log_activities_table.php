<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activités liées à une journée de journal (T-027, MLD §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_log_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('daily_log_id');
            $table->foreignUuid('activity_id');
            $table->foreignUuid('workspace_id');
            $table->text('contribution')->nullable();
            $table->timestamps();

            $table->unique(['daily_log_id', 'activity_id']);

            $table->foreign(['daily_log_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('daily_logs');

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_log_activities');
    }
};
