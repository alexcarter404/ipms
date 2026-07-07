import { expect, test } from '@playwright/test';
import { pickOptionIn } from './helpers.mjs';

test.describe('Reports & invoice exports', () => {
    test('a report is built, run, exported and saved with a schedule', async ({ page }) => {
        await page.goto('/reports');
        const builder = page.locator('[data-testid="report-builder"]');

        // Matters filtered to Acme
        await pickOptionIn(page, builder.locator('.p-select').nth(1), 'Acme Industries Ltd');
        await builder.getByRole('button', { name: 'Run Report' }).click();

        const results = page.locator('[data-testid="report-results"]');
        await expect(results.getByText('P-2021-0001')).toBeVisible();
        await expect(results.getByText('TM-2023-0001')).toHaveCount(0); // NovaTech's

        // CSV export carries the same rows
        const downloadPromise = page.waitForEvent('download');
        await builder.getByRole('link', { name: 'Download CSV' }).click();
        expect((await downloadPromise).suggestedFilename()).toBe('matters-report.csv');

        // Save with a daily schedule
        await builder.getByRole('button', { name: 'Save Report…' }).click();
        const modal = page.locator('.p-dialog');
        await modal.locator('input[type="text"]').fill('Acme portfolio');
        await pickOptionIn(page, modal.locator('.p-select'), 'Daily (weekday mornings)');
        await modal.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(page.getByText('Report “Acme portfolio” saved.')).toBeVisible();

        const saved = page.locator('[data-testid="saved-reports"]');
        await expect(saved.getByText('Acme portfolio')).toBeVisible();
        await expect(saved.locator('.p-tag', { hasText: 'daily' })).toBeVisible();

        // Clean up the saved report
        await saved.locator('li', { hasText: 'Acme portfolio' }).getByRole('button', { name: 'Delete' }).click();
        await page.locator('.p-confirmdialog').getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByText('Report deleted.')).toBeVisible();
    });

    test('invoices export as PDF and LEDES 1998B', async ({ page }) => {
        await page.goto('/billing/invoices');
        await page.getByRole('link', { name: 'INV-2026-0001' }).click();

        const pdfPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'PDF', exact: true }).click();
        expect((await pdfPromise).suggestedFilename()).toBe('INV-2026-0001.pdf');

        const ledesPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'LEDES', exact: true }).click();
        expect((await ledesPromise).suggestedFilename()).toBe('INV-2026-0001-ledes1998b.txt');
    });
});
