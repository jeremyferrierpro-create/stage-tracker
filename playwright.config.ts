import { defineConfig } from '@playwright/test';

/**
 * Tests navigateur (T-046, MT-06/MT-07/MT-08 — parcours critiques).
 *
 * Prérequis (voir README §19) :
 *   php artisan migrate:fresh --seed   # jeu de démo
 *   php artisan serve --host=127.0.0.1 --port=8000
 *
 * Lancer : npx playwright test
 * Rapport : npx playwright show-report (dossier e2e-report/)
 */
export default defineConfig({
    testDir: './e2e',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'e2e-report' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
        locale: 'fr-FR',
        trace: 'retain-on-failure',
    },
});
