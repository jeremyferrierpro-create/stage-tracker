<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Association commit ↔ activité (T-045, MLD §9). Plusieurs commits par
 * activité ; doublons interdits (UNIQUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_commits', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('activity_id');
            $table->foreignUuid('commit_id');
            $table->foreignUuid('workspace_id');
            $table->foreignId('linked_by')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('linked_at', 3)->useCurrent();

            $table->unique(['activity_id', 'commit_id']);

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities');

            $table->foreign(['commit_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('commits');

            $table->index(['workspace_id', 'commit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_commits');
    }
};
