import { expect, test } from '@playwright/test';
import { login, logout, openDemoWorkspace } from './helpers';

const PRIVATE_EVIDENCE = 'Notes personnelles de préparation';
const PRIVATE_JOURNAL = 'Notes personnelles du stagiaire (journal privé)';
const WORK_DONE = 'Finalisation de la maquette desktop';

/** Lien « Mes suivis » de la barre latérale (visible pour supervisor/tutor). */
function mesSuivisLink(page: import('@playwright/test').Page) {
    return page.getByRole('navigation', { name: 'Sections de l\'espace' }).getByRole('link', { name: 'Mes suivis' });
}

test.describe('Parcours critique — vue dédiée maître de stage (T-054)', () => {
    test('le maître de stage voit son périmètre sans aucune donnée privée', async ({ page }) => {
        await login(page, 'maitre-de-stage@example.test');
        await openDemoWorkspace(page);

        // 1. La sidebar expose « Mes suivis » pour un supervisor.
        await expect(mesSuivisLink(page)).toBeVisible();
        await mesSuivisLink(page).click();
        await expect(page).toHaveURL(/\/supervisor$/);

        // 2. Le périmètre affiche le stage démo (tiret cadratin ou demi-cadratin).
        await expect(
            page.getByRole('heading', { name: /Stage D[ée]mo [\u2013\u2014-] D[ée]veloppeur Web/ }),
        ).toBeVisible();

        // 3. Activités et preuve partagée visibles.
        await expect(page.getByRole('link', { name: 'Maquette de la page d\'accueil' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Intégration HTML/CSS' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Maquette desktop de la page d\'accueil' })).toBeVisible();

        // 4. Journal : le travail réalisé est visible, pas les notes privées.
        await expect(page.getByText(WORK_DONE)).toBeVisible();
        await expect(page.getByText(PRIVATE_JOURNAL)).toHaveCount(0);

        // 5. Aucune preuve privée (même rattachée à une activité du stage).
        await expect(page.getByText(PRIVATE_EVIDENCE)).toHaveCount(0);

        // 6. Capture d'écran de la vue dédiée (preuve de revue).
        await page.screenshot({ path: 'docs/screenshots/supervisor-dashboard.png', fullPage: true });

        await logout(page);
    });

    test('le stagiaire n\'a pas accès au lien ni à la vue dédiée', async ({ page }) => {
        await login(page, 'stagiaire@example.test');
        await openDemoWorkspace(page);

        await expect(mesSuivisLink(page)).toHaveCount(0);

        // Accès direct : le stagiaire n'est ni supervisor ni tutor dans
        // l'espace démo → la vue dédiée lui est refusée.
        const url = page.url().replace(/\/dashboard$/, '/supervisor');
        const response = await page.goto(url);
        expect(response?.status()).toBe(403);
    });
});
