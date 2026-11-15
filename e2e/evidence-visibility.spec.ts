import { expect, test } from '@playwright/test';
import { login, logout, openDemoWorkspace } from './helpers';

const SHARED = 'Maquette desktop de la page d\'accueil';
const PRIVATE = 'Notes personnelles de préparation';

/** Lien « Preuves » de la barre latérale (sans le fil d'Ariane). */
function preuvesLink(page: import('@playwright/test').Page) {
    return page.getByRole('navigation', { name: 'Sections de l\'espace' }).getByRole('link', { name: 'Preuves' });
}

test.describe('Parcours critique — visibilité des preuves (MT-07 / MT-08)', () => {
    test('le maître de stage voit la preuve partagée mais pas la preuve privée du stagiaire', async ({ page }) => {
        // 1. Le stagiaire voit ses deux preuves et copie l'URL de la privée.
        await login(page, 'stagiaire@example.test');
        await openDemoWorkspace(page);

        await preuvesLink(page).click();
        await expect(page.getByRole('cell', { name: SHARED })).toBeVisible();
        await expect(page.getByRole('cell', { name: PRIVATE })).toBeVisible();

        const privateUrl = await page
            .getByRole('row', { name: new RegExp(PRIVATE) })
            .getByRole('link', { name: 'Consulter' })
            .getAttribute('href');
        expect(privateUrl).toBeTruthy();

        // 2. Changement de compte : maître de stage.
        await logout(page);
        await login(page, 'maitre-de-stage@example.test');
        await openDemoWorkspace(page);

        // 3. MT-08 : la preuve partagée est consultable.
        await preuvesLink(page).click();
        await expect(page.getByRole('cell', { name: SHARED })).toBeVisible();
        await page.getByRole('row', { name: new RegExp(SHARED) }).getByRole('link', { name: 'Consulter' }).click();
        await expect(page.getByText(SHARED).first()).toBeVisible();
        await expect(page.getByText('https://www.figma.com/file/demo-maquette-accueil')).toBeVisible();

        // 4. MT-07 : la preuve privée n'apparaît pas dans la liste…
        await preuvesLink(page).click();
        await expect(page.getByRole('cell', { name: PRIVATE })).toHaveCount(0);

        // 5. …et son URL directe est refusée (403).
        const response = await page.goto(privateUrl!);
        expect(response?.status()).toBe(403);
    });
});
