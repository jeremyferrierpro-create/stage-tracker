<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine Identité — espaces de travail (MLD §2, ADR-002).
     */
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 120);
            $table->string('type', 20);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();

            $table->index('owner_id');
        });

        DB::statement(
            'ALTER TABLE workspaces ADD CONSTRAINT ck_workspaces_type '
            ."CHECK (type IN ('personal', 'association', 'professional', 'pedagogical'))"
        );

        DB::statement(
            'ALTER TABLE workspaces ADD CONSTRAINT ck_workspaces_status '
            ."CHECK (status IN ('active', 'archived'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
