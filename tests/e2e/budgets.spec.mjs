import { expect, test } from '@playwright/test';
import { field } from './helpers.mjs';

test.describe('Budgets', () => {
    test('a matter budget accumulates, is audited, and tracks consumption', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('link', { name: 'P-2021-0001' }).click();
        await page.getByRole('tab', { name: 'Billing' }).click();

        // Seeded: two accumulated entries totalling £1,500, audit-stamped
        const card = page.locator('[data-testid="budget-card"]');
        await expect(card.getByText('£1,500.00')).toBeVisible();
        await expect(card.getByText('Initial prosecution budget')).toBeVisible();
        await expect(card.getByText(/Alex Carter · /)).toBeVisible();
        await expect(card.getByText(/Jordan Reeves · /)).toBeVisible();

        // Add a new entry — the total accumulates
        await card.getByRole('button', { name: 'Add Budget' }).click();
        const dialog = page.locator('.p-dialog');
        await field(dialog, 'Amount').fill('500');
        await field(dialog, 'Description').fill('Grant phase top-up');
        await dialog.getByRole('button', { name: 'Add Budget' }).click();

        await expect(page.getByText('Budget added: GBP 500.00 on P-2021-0001.')).toBeVisible();
        await expect(card.getByText('£2,000.00')).toBeVisible();
        await expect(card.getByText('Grant phase top-up')).toBeVisible();
    });

    test('the budget dashboard shows utilisation across a portfolio', async ({ page }) => {
        await page.goto('/billing/budgets');

        // Defaults to my (Alex Carter's) portfolio — TM matter only
        await expect(page.locator('[data-testid="budget-row-TM-2023-0001"]')).toBeVisible();
        await expect(page.locator('[data-testid="budget-row-D-2024-0001"]')).toBeHidden();

        // Clearing the attorney filter reveals the whole firm
        await page.goto('/billing/budgets?user_id=');

        await expect(page.locator('[data-testid="budget-row-D-2024-0001"]')).toBeVisible();
        // The design matter is over budget: €1,200 consumed vs €1,000
        await expect(page.locator('[data-testid="budget-row-D-2024-0001"]')).toContainText('120%');
        await expect(page.locator('[data-testid="budget-row-D-2024-0001"]')).toContainText('-€200.00');

        // Top a budget up straight from the dashboard
        await page.locator('[data-testid="budget-row-D-2024-0001"]').getByRole('button', { name: 'Add budget' }).click();
        const dialog = page.locator('.p-dialog');
        await field(dialog, 'Amount').fill('400');
        await dialog.getByRole('button', { name: 'Add Budget' }).click();

        await expect(page.getByText('Budget added: EUR 400.00 on D-2024-0001.')).toBeVisible();
        await expect(page.locator('[data-testid="budget-row-D-2024-0001"]')).toContainText('€1,400.00');
    });
});
