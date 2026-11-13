<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version d'un référentiel (T-021, MLD §5).
 * `published_at` obligatoire si `published` (CHECK) ; la publication est
 * définitive : trigger d'immuabilité ajouté en T-022.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('framework_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('framework_id')->constrained('competency_frameworks')->cascadeOnDelete();
            $table->string('version', 40);
            $table->date('effective_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('published_at', 3)->nullable();
            $table->timestamps();

            $table->unique(['framework_id', 'version']);
        });

        DB::statement(
            "ALTER TABLE framework_versions
             ADD CONSTRAINT framework_versions_status_check
             CHECK (status IN ('draft', 'published', 'archived'))"
        );

        DB::statement(
            "ALTER TABLE framework_versions
             ADD CONSTRAINT framework_versions_published_has_date
             CHECK (status <> 'published' OR published_at IS NOT NULL)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('framework_versions');
    }
};
