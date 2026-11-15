import type { Page } from '@playwright/test';

export const DEMO_PASSWORD = 'mot-de-passe-demo-2026';

export async function login(page: Page, email: string, password: string = DEMO_PASSWORD): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Adresse e-mail').fill(email);
    await page.getByLabel('Mot de passe').fill(password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL('**/workspaces');
}

export async function logout(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Se déconnecter' }).click();
    await page.waitForURL('**/login');
}

/** Depuis « Mes espaces », ouvre l'espace de démonstration. */
export async function openDemoWorkspace(page: Page): Promise<void> {
    await page.getByRole('link', { name: /Ouvrir l'espace/ }).first().click();
    await page.waitForURL('**/dashboard');
}
