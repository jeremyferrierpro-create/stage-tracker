<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le rôle de participant `observer` (lecture seule) afin qu'un membre
 * workspace de rôle `viewer` puisse être rattaché à un stage sans pouvoir
 * créer de contenu (matrice rôles-permissions : portée « stage attribué »).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE internship_participants
             DROP CONSTRAINT internship_participants_role_check'
        );

        DB::statement(
            "ALTER TABLE internship_participants
             ADD CONSTRAINT internship_participants_role_check
             CHECK (role IN ('trainee', 'supervisor', 'tutor', 'observer'))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE internship_participants
             DROP CONSTRAINT internship_participants_role_check'
        );

        DB::statement(
            "ALTER TABLE internship_participants
             ADD CONSTRAINT internship_participants_role_check
             CHECK (role IN ('trainee', 'supervisor', 'tutor'))"
        );
    }
};
