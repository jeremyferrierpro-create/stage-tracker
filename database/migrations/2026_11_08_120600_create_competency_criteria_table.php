<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Critère évaluable d'une compétence (T-021, MLD §5).
 * Cascade sur la suppression de la compétence (version brouillon seulement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_criteria', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->string('label', 200);
            $table->text('description')->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->index('competency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_criteria');
    }
};
