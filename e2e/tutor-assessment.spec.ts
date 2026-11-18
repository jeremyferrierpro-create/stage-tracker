import { expect, test } from '@playwright/test';
import { login, logout, openDemoWorkspace } from './helpers';

/**
 * Parcours E2E « guide tuteur » (T-060/T-061) : le maître de stage
 * évalue nominativement une compétence mobilisée sur une activité.
 * Capture d'écran : docs/screenshots/tutor-assessment.png.
 */
test('guide tuteur : évaluer une compétence', async ({ page }) => {
    await login(page, 'maitre-de-stage@example.test');
    await openDemoWorkspace(page);

    // Fiche du stage de démonstration.
    await page
        .getByRole('navigation', { name: "Sections de l'espace" })
        .getByRole('link', { name: 'Stages' })
        .click();
    await page.waitForURL('**/internships');
    await page.getByRole('link', { name: 'Consulter' }).first().click();
    await page.waitForURL('**/internships/*');

    // Fiche du projet de démonstration.
    await page.getByRole('link', { name: 'Projets' }).first().click();
    await page.waitForURL('**/internships/*/projects');
    await page.getByRole('link', { name: 'Consulter' }).first().click();
    await page.waitForURL('**/projects/*');

    // Activité « Maquette de la page d'accueil » (compétence C1.1 mobilisée).
    await page.getByRole('link', { name: 'Consulter' }).first().click();
    await page.waitForURL('**/activities/*');
    await expect(page.getByRole('heading', { name: /Maquette de la page d'accueil/ })).toBeVisible();

    // Carte Compétences : C1.1 est « Mobilisée » et prêt à être évaluée.
    const competenciesCard = page.locator('.card').filter({ hasText: 'Compétences' });
    await expect(competenciesCard.getByText('C1.1')).toBeVisible();
    await expect(competenciesCard.getByText('Mobilisée').first()).toBeVisible();

    // Appréciation nominative (INV-09 : aucune validation automatique,
    // seule une évaluation explicite fait passer la compétence à « Évaluée »).
    const assessForm = competenciesCard.locator('form').filter({ hasText: 'Évaluer' });
    await assessForm
        .getByLabel("Statut de l'appréciation")
        .selectOption('validated');
    await assessForm
        .getByPlaceholder('Votre appréciation (nominative)')
        .fill('Communication claire et structurée avec le client pendant les revues de maquette. Maître de stage : excellent travail.');
    await assessForm.getByRole('button', { name: 'Évaluer' }).click();

    // L'appréciation est affichée, signée et la compétence est « Évaluée ».
    await expect(page.getByText(/Communication claire et structurée avec le client/).first()).toBeVisible();
    await expect(competenciesCard.getByText('Évaluée').first()).toBeVisible();

    // Capture d'écran pour le manuel utilisateur.
    await page.screenshot({ path: 'docs/screenshots/tutor-assessment.png', fullPage: true });

    await logout(page);
});
