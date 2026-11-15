<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien activité ↔ preuve (T-042, MLD §10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('activity_id');
            $table->foreignUuid('evidence_id');
            $table->foreignUuid('workspace_id');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'evidence_id']);

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities');

            $table->foreign(['evidence_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('evidences');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_evidences');
    }
};
