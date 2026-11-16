<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versions de modèles de rapports (T-062, MLD §13). `schema_json` porte la
 * trame (aucune trame codée en dur) ; une version publiée est immuable
 * (INV, même logique que les versions de référentiels).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_template_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('report_templates')->cascadeOnDelete();
            $table->string('version', 40);
            $table->jsonb('schema_json');
            $table->timestampTz('published_at', 3)->nullable();

            $table->unique(['template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_template_versions');
    }
};
