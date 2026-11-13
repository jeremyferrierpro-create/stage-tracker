<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programmes de formation (T-020, MLD §4).
 * Rattachée à précisément un workspace et à une organisation de l'espace
 * (FK composite) : une organisation d'un autre workspace est rejetée par
 * PostgreSQL (INV-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->comment('Organisme de formation (MLD §4, T-020)');
            $table->string('name', 160);
            $table->string('certification', 160)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['organization_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('organizations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};
