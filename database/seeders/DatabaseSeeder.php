<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Garde d'environnement : les seeders introduisent des comptes de
     * démonstration aux identifiants connus et ne doivent jamais s'exécuter
     * en production (risque T-010).
     */
    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            throw new RuntimeException(
                'Seeders interdits en dehors des environnements local et test.'
            );
        }

        $this->call(DemoSeeder::class);
    }
}
