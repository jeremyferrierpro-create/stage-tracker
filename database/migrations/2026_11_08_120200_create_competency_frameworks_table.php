<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel de compétences (racine, T-021, MLD §5, DEC-ST-003).
 * Ressource GLOBALE (multi-formations, hors workspace) — la version publiée
 * est partagée et immuable (T-022, INV-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_frameworks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160)->unique();
            $table->string('issuing_authority', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        DB::statement(
            "ALTER TABLE competency_frameworks
             ADD CONSTRAINT competency_frameworks_status_check
             CHECK (status IN ('draft', 'active', 'archived'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_frameworks');
    }
};
