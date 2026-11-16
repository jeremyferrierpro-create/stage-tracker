<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de rapports (T-062, MLD §13). Aucune trame codée en dur :
 * la structure vit dans les versions (schema_json). Statuts complémentaires
 * ('draft','published','archived') encadrant la publication — complément
 * MLD documenté dans le CHANGELOG 1.5.0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');

            $table->index(['workspace_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE report_templates
             ADD CONSTRAINT report_templates_type_check
             CHECK (type IN ('memoir', 'professional_file', 'oral_presentation', 'personal_review', 'technical_review', 'weekly_followup', 'supervisor_followup'))"
        );

        DB::statement(
            "ALTER TABLE report_templates
             ADD CONSTRAINT report_templates_status_check
             CHECK (status IN ('draft', 'published', 'archived'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
