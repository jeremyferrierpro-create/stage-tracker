import { expect, test } from '@playwright/test';
import { login, openDemoWorkspace } from './helpers';

test.describe('Parcours critique — traçabilité commits ↔ activités (MT-06)', () => {
    test('du tableau de bord à la fiche dépôt : les commits importés sont visibles', async ({ page }) => {
        await login(page, 'stagiaire@example.test');
        await openDemoWorkspace(page);

        // Tableau de bord → stages.
        await page.getByRole('link', { name: 'Stages', exact: true }).first().click();
        await expect(page.getByText(/Stage Démo [\u2013\u2014-] Développeur Web/)).toBeVisible();

        // Stage → projets → projet.
        await page.getByRole('link', { name: 'Consulter' }).first().click();
        await page.getByRole('link', { name: 'Projets' }).first().click();
        await expect(page.getByText(/Projet Démo [\u2013\u2014-] Site vitrine/)).toBeVisible();
        await page.getByRole('link', { name: 'Consulter' }).first().click();

        // Fiche projet → section Dépôts Git.
        await expect(page.getByRole('heading', { name: 'Dépôts Git' })).toBeVisible();
        await page.getByRole('link', { name: /Dépôt Démo [\u2013\u2014-] Site vitrine/ }).click();

        // Fiche dépôt : les 3 commits du jeu de démo, dont un lié à l'activité.
        await expect(page.getByRole('heading', { name: 'Commits importés' })).toBeVisible();
        await expect(page.getByText('feat: maquette de la page d\'accueil')).toBeVisible();
        await expect(page.getByText('feat: intégration HTML/CSS responsive')).toBeVisible();
        await expect(page.getByText('fix: correctif d\'accessibilité des liens')).toBeVisible();
        await expect(page.getByText(/lié\s*:\s*Maquette de la page d'accueil/)).toBeVisible();
    });
});
