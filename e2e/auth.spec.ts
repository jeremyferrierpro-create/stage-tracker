import { expect, test } from '@playwright/test';
import { DEMO_PASSWORD, login, logout } from './helpers';

test.describe('Parcours critique — authentification', () => {
    test('connexion avec un compte de démonstration puis déconnexion', async ({ page }) => {
        // Pas d'inscription publique : le lien de connexion mène au formulaire.
        await page.goto('/');
        await page.waitForURL('**/login');
        await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible();

        await login(page, 'stagiaire@example.test');
        await expect(page.getByRole('heading', { name: 'Mes espaces de travail' })).toBeVisible();
        await expect(page.getByText('Espace Démo')).toBeVisible();

        await logout(page);
        await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible();
    });

    test('mot de passe erroné — message d\'erreur explicite', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Adresse e-mail').fill('stagiaire@example.test');
        await page.getByLabel('Mot de passe').fill('mauvais-mot-de-passe');
        await page.getByRole('button', { name: 'Se connecter' }).click();

        await expect(page.getByText(/identifiants|incorrect|erron/)).toBeVisible();
        expect(DEMO_PASSWORD).not.toContain('mauvais');
    });
});
