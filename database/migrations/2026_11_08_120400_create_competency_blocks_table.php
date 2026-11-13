<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloc de compétences d'une version de référentiel (T-021, MLD §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('framework_version_id')->constrained('framework_versions')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['framework_version_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_blocks');
    }
};
