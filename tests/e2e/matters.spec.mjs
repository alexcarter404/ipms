import { expect, test } from '@playwright/test';
import { field, pickOption, pickOptionIn } from './helpers.mjs';

test.describe('Matters', () => {
    test('index lists seeded matters and filters by type', async ({ page }) => {
        await page.goto('/matters');

        await expect(page.getByRole('link', { name: 'P-2021-0001' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'TM-2023-0001' })).toBeVisible();

        // Filter down to trade marks only
        await pickOptionIn(page, page.locator('.p-select', { hasText: 'All types' }), 'Trade Mark');
        await expect(page.getByRole('link', { name: 'TM-2023-0001' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'P-2021-0001' })).toBeHidden();
    });

    test('search finds matters by reference', async ({ page }) => {
        await page.goto('/matters');

        await page.getByPlaceholder('Search ref, title, number, client…').fill('D-2024');
        await expect(page.getByRole('link', { name: 'D-2024-0001' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'TM-2023-0001' })).toBeHidden();
    });

    test('matter detail shows overview, family relations and tabs', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('link', { name: 'P-2021-0001' }).click();

        await expect(page.getByRole('heading', { name: 'P-2021-0001' })).toBeVisible();
        await expect(page.getByText('Self-sealing valve assembly').first()).toBeVisible();
        await expect(page.getByText('Official Numbers & Dates')).toBeVisible();
        // Family child matters listed
        await expect(page.getByRole('link', { name: 'P-2021-0002' })).toBeVisible();

        // Parties tab shows seeded inventors/agents
        await page.getByRole('button', { name: /Parties \(/ }).click();
        await expect(page.getByRole('heading', { name: 'inventors' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'agents' })).toBeVisible();

        // Renewals tab shows generated schedule
        await page.getByRole('button', { name: /Renewals \(/ }).click();
        await expect(page.getByRole('button', { name: 'Generate Schedule' })).toBeVisible();
        await expect(page.locator('table tbody tr').first()).toBeVisible();
    });

    test('trademark detail shows Nice classes tab', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('link', { name: 'TM-2023-0001' }).click();

        await page.getByRole('button', { name: /Classes \(/ }).click();
        await expect(page.getByText('Computer software; firewalls')).toBeVisible();
    });

    test('a matter can be created end-to-end', async ({ page }) => {
        await page.goto('/matters/create');

        await field(page, 'Reference').fill('E2E-0001');
        await pickOption(page, page, 'Type', 'Patent');
        await pickOption(page, page, 'Status', /^Filed$/);
        await field(page, 'Title').fill('Playwright-created invention');
        await pickOption(page, page, 'Jurisdiction', 'GB — United Kingdom');
        await field(page, 'Client', 'select').click();
        await page.locator('.p-select-overlay [role="option"]').first().click();
        await field(page, 'Application date').fill('2026-05-01');

        await page.getByRole('button', { name: 'Create Matter' }).click();

        await expect(page.getByRole('heading', { name: 'E2E-0001' })).toBeVisible();
        await expect(page.getByText('Matter created.')).toBeVisible();
    });

    test('validation errors are shown on bad input', async ({ page }) => {
        await page.goto('/matters/create');

        // Duplicate reference + missing required fields
        await field(page, 'Reference').fill('P-2021-0001');
        await page.getByRole('button', { name: 'Create Matter' }).click();

        await expect(page.getByText('The reference has already been taken.')).toBeVisible();
    });
});
