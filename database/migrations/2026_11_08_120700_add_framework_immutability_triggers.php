<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Immuabilité d'une version publiée (T-022, MLD §5, INV-08).
 *
 * 1. `framework_versions` : refuse tout UPDATE d'une ligne déjà `published`
 *    et tout DELETE de cette ligne (la transition draft → published reste
 *    permise : c'est l'opération de publication).
 * 2. `competency_blocks`, `competencies`, `competency_criteria` : refuse tout
 *    UPDATE/DELETE tant que la version parente est `published`.
 *
 * Le refus est appliqué au niveau PostgreSQL : même un accès direct en SQL
 * ne peut pas modifier une version publiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION framework_version_guard() RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'published' THEN
                    RAISE EXCEPTION 'framework_versions % est publiée et immuable (INV-08)', OLD.id;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(
            "CREATE OR REPLACE FUNCTION framework_content_immutable() RETURNS trigger AS \$\$
            DECLARE
                v_version_id uuid;
            BEGIN
                v_version_id := CASE TG_TABLE_NAME
                    WHEN 'competency_blocks' THEN COALESCE(NEW.framework_version_id, OLD.framework_version_id)
                    ELSE NULL
                END;

                IF TG_TABLE_NAME = 'competencies' THEN
                    v_version_id := COALESCE(
                        (CASE WHEN TG_OP = 'UPDATE' THEN NEW.framework_version_id END),
                        OLD.framework_version_id
                    );
                END IF;

                IF TG_TABLE_NAME = 'competency_criteria' THEN
                    v_version_id := (SELECT framework_version_id FROM competencies WHERE id = OLD.competency_id);
                END IF;

                IF v_version_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM framework_versions
                    WHERE id = v_version_id AND status = 'published'
                ) THEN
                    RAISE EXCEPTION '% % est rattaché à une version publiée : immuable (INV-08)', TG_TABLE_NAME, COALESCE(OLD.id, NEW.id);
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            \$\$ LANGUAGE plpgsql",
        );

        DB::statement(
            'CREATE TRIGGER framework_versions_immutable
             BEFORE UPDATE OR DELETE ON framework_versions
             FOR EACH ROW EXECUTE FUNCTION framework_version_guard()'
        );

        DB::statement(
            'CREATE TRIGGER competency_blocks_immutable
             BEFORE UPDATE OR DELETE ON competency_blocks
             FOR EACH ROW EXECUTE FUNCTION framework_content_immutable()'
        );

        DB::statement(
            'CREATE TRIGGER competencies_immutable
             BEFORE UPDATE OR DELETE ON competencies
             FOR EACH ROW EXECUTE FUNCTION framework_content_immutable()'
        );

        DB::statement(
            'CREATE TRIGGER competency_criteria_immutable
             BEFORE UPDATE OR DELETE ON competency_criteria
             FOR EACH ROW EXECUTE FUNCTION framework_content_immutable()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS framework_versions_immutable ON framework_versions');
        DB::statement('DROP TRIGGER IF EXISTS competency_blocks_immutable ON competency_blocks');
        DB::statement('DROP TRIGGER IF EXISTS competencies_immutable ON competencies');
        DB::statement('DROP TRIGGER IF EXISTS competency_criteria_immutable ON competency_criteria');

        DB::statement('DROP FUNCTION IF EXISTS framework_version_guard()');
        DB::statement('DROP FUNCTION IF EXISTS framework_content_immutable()');
    }
};
