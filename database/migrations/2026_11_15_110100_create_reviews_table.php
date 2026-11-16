<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revues d'activité (T-051, MLD §12). La demande (created_at) est immuable ;
 * seuls le statut et le feedback évoluent, avec transitions contrôlées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id');
            $table->foreignUuid('workspace_id');
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('requested');
            $table->text('feedback')->nullable();
            $table->timestampTz('created_at', 3)->useCurrent();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['activity_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('activities');

            $table->index(['workspace_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE reviews
             ADD CONSTRAINT reviews_status_check
             CHECK (status IN ('requested', 'in_progress', 'approved', 'changes_requested'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
