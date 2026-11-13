<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compétence d'une version de référentiel, rattachée optionnellement à un
 * bloc et à une compétence parente (hiérarchie, T-021, MLD §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('framework_version_id')->constrained('framework_versions')->cascadeOnDelete();
            $table->foreignUuid('block_id')->nullable()
                ->constrained('competency_blocks')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['framework_version_id', 'code']);
            $table->index('parent_id');
        });

        DB::statement(
            'ALTER TABLE competencies
             ADD CONSTRAINT competencies_parent_id_foreign
             FOREIGN KEY (parent_id) REFERENCES competencies (id)
             ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('competencies');
    }
};
