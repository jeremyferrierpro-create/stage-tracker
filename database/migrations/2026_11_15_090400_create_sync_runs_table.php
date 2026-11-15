<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historique de synchronisation (T-044, MLD §9). Chaque exécution est
 * tracée, y compris les exécutions partielles ou en échec.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id');
            $table->string('provider', 20)->default('github');
            $table->timestampTz('started_at', 3)->nullable();
            $table->timestampTz('completed_at', 3)->nullable();
            $table->string('status', 20)->default('running');
            $table->integer('items_imported')->default(0);
            $table->text('error_summary')->nullable();

            $table->foreign('repository_id')
                ->references('id')
                ->on('repositories')
                ->cascadeOnDelete();

            $table->index(['repository_id', 'started_at']);
        });

        DB::statement(
            "ALTER TABLE sync_runs
             ADD CONSTRAINT sync_runs_status_check
             CHECK (status IN ('running', 'success', 'partial', 'failed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
