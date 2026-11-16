<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rapports générés (T-063, MLD §13). L'asynchrone est matérialisé par le
 * statut `queued` ; le fichier généré vit sur le disque privé (INV-14 :
 * filtrage de visibilité à la génération, exclusions listées et auditées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('internship_id');
            $table->foreignUuid('workspace_id');
            $table->foreignUuid('template_version_id')->constrained('report_template_versions')->cascadeOnDelete();
            $table->foreignId('generated_by')->constrained('users');
            $table->timestampTz('generated_at', 3)->useCurrent();
            $table->string('file_path', 500)->nullable();
            $table->string('status', 20)->default('queued');

            $table->unique(['id', 'workspace_id']);

            $table->foreign(['internship_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('internships');

            $table->index(['workspace_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE reports
             ADD CONSTRAINT reports_status_check
             CHECK (status IN ('queued', 'generated', 'failed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
