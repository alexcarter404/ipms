import { expect, test } from '@playwright/test';
import { field, pickOption } from './helpers.mjs';

test.describe('Renewal schedule rules', () => {
    test('rules page is reachable from Renewals and lists seeded templates', async ({ page }) => {
        await page.goto('/renewals');
        await page.getByRole('link', { name: 'Schedule Rules' }).click();

        await expect(page.getByRole('heading', { name: 'Renewal Schedule Rules' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Patent Annuities (default)' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'US Patent Maintenance Fees' })).toBeVisible();
        // Country-specific vs type-wide default is visible at a glance
        await expect(page.getByText('Any (default)').first()).toBeVisible();
        await expect(page.getByText('Due at 3.5y, 7.5y, 11.5y from grant/registration')).toBeVisible();
    });

    test('a regular-cycle rule can be created with live preview', async ({ page }) => {
        await page.goto('/renewal-rules/create');

        await field(page, 'Name *').fill('JP Patent Annuities');
        await pickOption(page, page, 'Matter type *', /^Patent$/);
        await pickOption(page, page, 'Jurisdiction', 'JP — Japan');
        await pickOption(page, page, 'Anchor date *', 'Registration / grant date');
        await field(page, 'First cycle *').fill('1');
        await field(page, 'Last cycle *').fill('20');
        await field(page, 'Interval (years) *').fill('1');

        // Live schedule preview reflects the inputs
        await expect(page.getByText(/Cycles 1–20: due 1y, 2y/)).toBeVisible();

        await page.getByRole('button', { name: 'Create Rule' }).click();

        await expect(page.getByText('Renewal rule created.')).toBeVisible();
        await expect(page.getByRole('link', { name: 'JP Patent Annuities' })).toBeVisible();
    });

    test('a fixed-offsets rule can be created', async ({ page }) => {
        await page.goto('/renewal-rules/create');

        await field(page, 'Name *').fill('CA Patent Maintenance');
        await pickOption(page, page, 'Matter type *', /^Patent$/);
        await pickOption(page, page, 'Jurisdiction', 'CA — Canada');
        await page.getByRole('radio', { name: /Fixed offsets/ }).check();

        await page.getByRole('button', { name: 'Add Due Date' }).click();
        await page.getByPlaceholder('Months').fill('24');

        await page.getByRole('button', { name: 'Create Rule' }).click();

        await expect(page.getByText('Renewal rule created.')).toBeVisible();
        const row = page.locator('tr', { hasText: 'CA Patent Maintenance' });
        await expect(row.getByText('Due at 2y from filing')).toBeVisible();
    });

    test('matter renewals tab names its governing rule', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('link', { name: 'P-2021-0001' }).click();
        await page.getByRole('button', { name: /Renewals \(/ }).click();

        await expect(page.getByText('Governed by')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Patent Annuities (default)' })).toBeVisible();
    });
});
