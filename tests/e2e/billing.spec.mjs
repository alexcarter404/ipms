import { expect, test } from '@playwright/test';
import { field, pickOption } from './helpers.mjs';

test.describe('Billing', () => {
    test('time and disbursements are billed through to a paid invoice', async ({ page }) => {
        // P-2021-0002: seeded capped agreement (GBP, VAT-registered entity)
        await page.goto('/matters');
        await page.getByRole('link', { name: 'P-2021-0002' }).click();
        await page.getByRole('tab', { name: 'Billing' }).click();

        await expect(page.getByText('Capped Fee')).toBeVisible();

        // Log an hour for Alex Carter (rate card: GBP 320/h)
        await page.getByRole('button', { name: 'Log Time' }).click();
        const dialog = page.locator('.p-dialog');
        await pickOption(page, dialog, 'Timekeeper', 'Alex Carter');
        await field(dialog, 'Minutes worked').fill('60');
        await field(dialog, 'Narrative', 'textarea').fill('Draft response to examination report');
        await dialog.getByRole('button', { name: 'Log Time' }).click();

        await expect(page.getByText('Time logged: 60m billed as 60m (GBP 320.00).')).toBeVisible();
        await expect(page.locator('tr', { hasText: 'Draft response to examination report' })).toContainText('£320.00');

        // Add a marked-up disbursement
        await page.getByRole('button', { name: 'Add Disbursement' }).click();
        await field(dialog, 'Description').fill('Official search fee');
        await field(dialog, 'Cost amount').fill('100');
        await field(dialog, 'Markup %').fill('15');
        await dialog.getByRole('button', { name: 'Add Disbursement' }).click();

        await expect(page.getByText('Disbursement added — billed at GBP 115.00.')).toBeVisible();
        await expect(page.locator('[data-testid="wip-summary"]')).toContainText('£435.00');

        // Draft the invoice from WIP
        await page.getByRole('button', { name: 'Draft Invoice' }).click();
        await dialog.getByRole('button', { name: 'Create Draft' }).click();

        // 435 + 20% VAT = 522
        await expect(page.getByText('Draft invoice created — 2 line(s), GBP 522.00.')).toBeVisible();
        await expect(page.getByRole('heading', { name: /Draft #\d+/ })).toBeVisible();
        await expect(page.getByText('£522.00').first()).toBeVisible();

        // Issue it (assigns the next sequential number)
        await page.getByRole('button', { name: 'Issue Invoice' }).click();
        await page.locator('.p-confirmdialog').getByRole('button', { name: 'Issue' }).click();
        await expect(page.getByText(/Invoice issued as INV-\d{4}-\d{4}\./)).toBeVisible();

        // Record full payment — invoice settles
        await page.getByRole('button', { name: 'Record Payment' }).click();
        await field(dialog, 'Amount (GBP)').fill('522');
        await dialog.getByRole('button', { name: 'Record Payment' }).click();

        await expect(page.getByText('Payment recorded — invoice settled in full.')).toBeVisible();
        await expect(page.getByRole('banner').getByText('Paid')).toBeVisible();
    });

    test('a quote is built with lines and tax, then accepted', async ({ page }) => {
        await page.goto('/quotes/create');

        await pickOption(page, page, 'Client *', 'Acme Industries Ltd');
        await pickOption(page, page, 'Currency *', 'EUR — Euro');
        await pickOption(page, page, 'Tax treatment', 'UK VAT (standard)');

        await page.getByPlaceholder('Description').fill('EP validation programme');
        await page.getByPlaceholder('Unit amount').fill('5000');
        await page.getByRole('button', { name: 'Add Line' }).click();
        await page.getByPlaceholder('Description').nth(1).fill('National agent fees');
        await page.getByPlaceholder('Qty').nth(1).fill('4');
        await page.getByPlaceholder('Unit amount').nth(1).fill('250');

        // Live totals: 6,000 + 20% tax
        await expect(page.getByText('€7,200.00')).toBeVisible();

        await page.getByRole('button', { name: 'Create Quote' }).click();
        await expect(page.getByText(/Quote Q-\d{4}-\d{4} created\./)).toBeVisible();
        await expect(page.getByRole('heading', { name: /Quote Q-\d{4}-\d{4}/ })).toBeVisible();

        await page.getByRole('button', { name: 'Mark Sent' }).click();
        await expect(page.getByRole('banner').getByText('Sent')).toBeVisible();

        await page.getByRole('button', { name: 'Mark Accepted' }).click();
        await expect(page.getByRole('banner').getByText('Accepted')).toBeVisible();
        await expect(page.getByText('can no longer be edited')).toBeVisible();
    });

    test('billing settings manage exchange rates, tax and rate cards', async ({ page }) => {
        await page.goto('/billing/settings');

        // Seeded ECB-style rates against the GBP base
        await expect(page.locator('tr', { hasText: 'EUR' })).toContainText('1.17');

        // Set a manual rate
        await pickOption(page, page, 'Currency', 'EUR — Euro');
        await field(page, '1 GBP equals').fill('1.25');
        await page.getByRole('button', { name: 'Save Rate' }).click();
        await expect(page.getByText('Rate saved for EUR.')).toBeVisible();
        await expect(page.locator('tr', { hasText: 'EUR' })).toContainText('1.25');

        // Tax rates tab
        await page.getByRole('tab', { name: 'Tax Rates' }).click();
        await expect(page.locator('tr', { hasText: 'UK VAT (standard)' })).toContainText('20%');

        // Rate rules tab: personal, grade-based and firm-default rules
        await page.getByRole('tab', { name: 'Rate Rules' }).click();
        await expect(page.locator('tr', { hasText: 'Alex Carter' })).toContainText('GBP 320.00');
        await expect(page.locator('tr', { hasText: 'Any Attorney' })).toContainText('GBP 240.00');
        await expect(page.locator('tr', { hasText: 'Any timekeeper' })).toContainText('GBP 250.00');
        await expect(page.getByRole('heading', { name: 'Timekeeper grades' })).toBeVisible();
    });
});
