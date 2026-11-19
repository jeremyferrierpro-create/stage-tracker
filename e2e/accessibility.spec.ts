import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { login, logout, openDemoWorkspace } from './helpers';

/**
 * Audit d'accessibilité (T-066) — axe-core sur les pages clés.
 * Exigence : aucun défaut impact « serious » ou « critical »
 * (WCAG 2.1 A + AA).
 */

async function audit(page: import('@playwright/test').Page): Promise<string[]> {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

    return results.violations
        .filter((v) => v.impact === 'serious' || v.impact === 'critical')
        .map((v) => `${v.id} [${v.impact}] : ${v.nodes.length} nœud(s) — ex. ${v.nodes[0]?.target.join(' ') ?? '?'}`);
}

test.describe('Audit accessibilité axe-core (T-066)', () => {
    test('page de connexion (publique)', async ({ page }) => {
        await page.goto('/login');
        expect(await audit(page)).toEqual([]);
    });

    test('pages de l\'administrateur', async ({ page }) => {
        await login(page, 'admin@example.test');
        await openDemoWorkspace(page);
        expect(await audit(page)).toEqual([]);

        // Liste des stages.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: 'Stages' }).click();
        await page.waitForURL('**/internships');
        expect(await audit(page)).toEqual([]);

        // Fiche du stage de démonstration.
        await page.getByRole('link', { name: 'Consulter' }).first().click();
        await page.waitForURL('**/internships/*');
        expect(await audit(page)).toEqual([]);

        // Liste des projets puis fiche du projet, puis activité.
        await page.getByRole('link', { name: 'Projets' }).first().click();
        await page.waitForURL('**/internships/*/projects');
        expect(await audit(page)).toEqual([]);

        await page.getByRole('link', { name: 'Consulter' }).first().click();
        await page.waitForURL('**/projects/*');
        expect(await audit(page)).toEqual([]);

        await page.getByRole('link', { name: 'Consulter' }).first().click();
        await page.waitForURL('**/activities/*');
        expect(await audit(page)).toEqual([]);

        // Compétences de l'espace.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: 'Compétences' }).click();
        await page.waitForURL('**/competencies');
        expect(await audit(page)).toEqual([]);

        // Rapports du stage.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: 'Stages' }).click();
        await page.waitForURL('**/internships');
        await page.getByRole('link', { name: 'Consulter' }).first().click();
        await page.waitForURL('**/internships/*');
        await page.locator('#contenu').getByRole('link', { name: 'Rapports' }).click();
        await page.waitForURL('**/reports');
        expect(await audit(page)).toEqual([]);

        // Membres et invitations.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: 'Membres et invitations' }).click();
        await page.waitForURL('**/members');
        expect(await audit(page)).toEqual([]);

        // Modèles de rapports.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: 'Modèles de rapports' }).click();
        await page.waitForURL('**/report-templates');
        expect(await audit(page)).toEqual([]);

        // Journal d'audit.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: "Journal d'audit" }).click();
        await page.waitForURL('**/audit');
        expect(await audit(page)).toEqual([]);

        // Preuves.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: 'Preuves' }).click();
        await page.waitForURL('**/evidences');
        expect(await audit(page)).toEqual([]);

        await logout(page);
    });

    test('pages du stagiaire (journal et preuves)', async ({ page }) => {
        await login(page, 'stagiaire@example.test');
        await openDemoWorkspace(page);

        // Fiche du stage : le journal y est accessible.
        await page.getByRole('navigation', { name: "Sections de l'espace" }).getByRole('link', { name: 'Stages' }).click();
        await page.waitForURL('**/internships');
        expect(await audit(page)).toEqual([]);

        await page.getByRole('link', { name: 'Consulter' }).first().click();
        await page.waitForURL('**/internships/*');
        expect(await audit(page)).toEqual([]);

        // Journal du stage.
        await page.getByRole('link', { name: 'Journal' }).first().click();
        await page.waitForURL('**/daily-logs');
        expect(await audit(page)).toEqual([]);

        await logout(page);
    });
});
