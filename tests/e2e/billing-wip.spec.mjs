import { expect, test } from '@playwright/test';
import { field, pickOption } from './helpers.mjs';

test.describe('WIP dashboard & consolidated billing', () => {
    test('entity WIP is summarised, reviewed, amended and billed in one flow', async ({ page }) => {
        // Put WIP on a second Acme matter so the consolidated bill spans two
        await page.goto('/matters');
        await page.getByRole('link', { name: 'P-2021-0002' }).click();
        await page.getByRole('tab', { name: 'Billing' }).click();
        await page.getByRole('button', { name: 'Log Time' }).click();
        const dialog = page.locator('.p-dialog');
        await pickOption(page, dialog, 'Timekeeper', 'Jordan Reeves');
        await field(dialog, 'Minutes worked').fill('60');
        await field(dialog, 'Narrative', 'textarea').fill('EP examination review');
        await dialog.getByRole('button', { name: 'Log Time' }).click();
        await expect(page.getByText('Time logged: 60m billed as 60m (GBP 260.00).')).toBeVisible();

        // The dashboard is one compact row per entity: total + age
        await page.goto('/billing/wip');
        const acmeRow = page.locator('tr', { hasText: 'Acme Industries Ltd' });
        await expect(acmeRow).toContainText('2');           // matters
        await expect(acmeRow).toContainText('£1,484.91');   // 1,224.91 + 260.00
        await expect(acmeRow).toContainText(/day|Today/);   // age indication
        await expect(page.locator('tr', { hasText: 'NovaTech GmbH' })).toContainText('€1,200.00');

        // Drill in to review the entity's items
        await acmeRow.getByRole('link', { name: 'Review & bill →' }).click();
        await expect(page.getByRole('heading', { name: 'Acme Industries Ltd' })).toBeVisible();
        await expect(page.getByText('£1,484.91')).toBeVisible();
        await expect(page.locator('[data-testid="wip-matter-P-2021-0001"]')).toBeVisible();
        await expect(page.locator('[data-testid="wip-matter-P-2021-0002"]')).toBeVisible();

        // Amend a disbursement's wording before it hits the invoice
        await page
            .locator('tr', { hasText: 'EPO examination fee' })
            .getByRole('button', { name: 'Amend' })
            .click();
        // The row's text is now inside the edit input, so re-anchor on it
        const editRow = page.locator('tr', { has: page.getByRole('button', { name: 'Save', exact: true }) });
        await editRow.locator('input[type="text"]').fill('EPO examination fee (official receipt 4471)');
        await editRow.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(page.getByText('Disbursement updated.')).toBeVisible();
        await expect(page.getByText('EPO examination fee (official receipt 4471)')).toBeVisible();

        // Bill both matters in one consolidated invoice
        await page.getByRole('button', { name: 'Draft Invoice (all)' }).click();
        await expect(
            page.getByText('Consolidated draft created — 4 line(s) across 2 matter(s), GBP 1,781.89.')
        ).toBeVisible();
        await expect(page.getByText('EPO examination fee (official receipt 4471)')).toBeVisible();

        // Billed WIP has left the dashboard; NovaTech remains
        await page.goto('/billing/wip');
        await expect(page.locator('tr', { hasText: 'Acme Industries Ltd' })).toBeHidden();
        await expect(page.locator('tr', { hasText: 'NovaTech GmbH' })).toBeVisible();
    });

    test('a single matter can be billed from the entity drill-in', async ({ page }) => {
        // NovaTech's stage charge is still unbilled from the seed data
        await page.goto('/billing/wip');
        await page.locator('tr', { hasText: 'NovaTech GmbH' }).getByRole('link', { name: 'Review & bill →' }).click();

        await page
            .locator('[data-testid="wip-matter-D-2024-0001"]')
            .getByRole('button', { name: 'Bill this matter' })
            .click();

        await expect(
            page.getByText('Consolidated draft created — 1 line(s) across 1 matter(s), EUR 1,200.00.')
        ).toBeVisible();
        await expect(page.getByText('Design search & clearance')).toBeVisible();
    });
});
