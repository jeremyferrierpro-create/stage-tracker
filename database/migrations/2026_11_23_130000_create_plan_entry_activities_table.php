<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien many-to-many entre une entrée de planning prévisionnel
 * (plan_entries) et les activités réelles qui la réalisent.
 * Une entrée peut être réalisée par plusieurs activités et une
 * activité peut couvrir plusieurs entrées planifiées.
 * Les preuves se déposent ensuite sur l'activité (activity_evidences).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_entry_activities', function (Blueprint $table): void {
            $table->foreignUuid('plan_entry_id');
            $table->foreignUuid('activity_id');
            $table->foreignUuid('workspace_id');
            $table->timestamps();

            $table->unique(['plan_entry_id', 'activity_id']);

            $table->foreign(['plan_entry_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('plan_entries')
                ->cascadeOnDelete();

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_entry_activities');
    }
};
