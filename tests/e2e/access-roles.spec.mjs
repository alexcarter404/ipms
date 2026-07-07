import { expect, test } from '@playwright/test';
import { pickOptionIn } from './helpers.mjs';

test.describe('Access control', () => {
    test('admins manage access roles from Users & Access', async ({ page }) => {
        await page.goto('/settings/users');

        const jordan = page.locator('tr', { hasText: 'Jordan Reeves' });
        await expect(jordan).toContainText('jordan@example.com');

        // Grade and access role are inline editable
        await pickOptionIn(page, jordan.locator('.p-select').nth(1), 'Finance');
        await expect(page.getByText('Access updated for Jordan Reeves.')).toBeVisible();

        // ...and back, leaving the seeded state untouched
        await pickOptionIn(page, jordan.locator('.p-select').nth(1), 'Professional');
        await expect(page.getByText('Access updated for Jordan Reeves.').first()).toBeVisible();

        // The last admin cannot be demoted
        const alex = page.locator('tr', { hasText: 'Alex Carter' });
        await pickOptionIn(page, alex.locator('.p-select').nth(1), 'Read-only');
        await expect(page.getByText('There must be at least one administrator.')).toBeVisible();
    });

    test('an ethical wall is raised and lowered on a client', async ({ page }) => {
        await page.goto('/clients');
        await page.getByRole('link', { name: 'NOVA', exact: true }).click();

        const panel = page.locator('[data-testid="wall-panel"]');
        await panel.locator('.p-multiselect').click();
        await page.locator('.p-multiselect-overlay [role="option"]', { hasText: 'Alex Carter' }).click();
        await page.keyboard.press('Escape');
        await panel.getByRole('button', { name: 'Save Wall' }).click();
        await expect(
            page.getByText('Access to NovaTech GmbH is now restricted to 1 user(s) plus administrators.')
        ).toBeVisible();

        // Lower it again so the rest of the suite sees the client
        await panel.locator('.p-multiselect').click();
        await page.locator('.p-multiselect-overlay [role="option"]', { hasText: 'Alex Carter' }).click();
        await page.keyboard.press('Escape');
        await panel.getByRole('button', { name: 'Save Wall' }).click();
        await expect(page.getByText('Wall removed — NovaTech GmbH is visible to everyone.')).toBeVisible();
    });

    test('the conflict check flags existing names at intake', async ({ page }) => {
        await page.goto('/clients/create');

        await page.locator('div:has(> label:has-text("Name *")) input').first().fill('Acme Industries');
        const panel = page.locator('[data-testid="conflict-check"]');
        await panel.getByRole('button', { name: 'Run Conflict Check' }).click();

        await expect(panel.getByText('Acme Industries Ltd').first()).toBeVisible();
        await expect(panel.locator('.p-tag', { hasText: 'Client' }).first()).toBeVisible();
    });
});
