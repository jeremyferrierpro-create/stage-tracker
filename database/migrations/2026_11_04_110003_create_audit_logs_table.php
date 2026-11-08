<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine Audit — journal append-only (MLD §14, SECURITY §3.6).
     *
     * Aucun UPDATE/DELETE n'est autorisé : un trigger PostgreSQL rejette toute
     * mutation, même en cas de bug applicatif ou de requête directe.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('workspace_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 120)->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // Pas de FK sur workspace_id : l'audit doit survivre à l'archivage ou à
        // la suppression d'un workspace.
        DB::statement(
            'CREATE INDEX ix_audit_workspace_created '
            .'ON audit_logs (workspace_id, created_at DESC)'
        );

        DB::statement(
            'CREATE OR REPLACE FUNCTION audit_logs_prevent_mutation() RETURNS trigger '
            .'LANGUAGE plpgsql AS $$ '
            .'BEGIN '
            ."RAISE EXCEPTION 'audit_logs est append-only : opération % refusée', TG_OP; "
            .'END; '
            .'$$'
        );

        DB::statement(
            'CREATE TRIGGER audit_logs_append_only '
            .'BEFORE UPDATE OR DELETE ON audit_logs '
            .'FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs');
        Schema::dropIfExists('audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_prevent_mutation()');
    }
};
