import { expect, test } from '@playwright/test';
import { field, pickOption } from './helpers.mjs';

test.describe('WIP dashboard & consolidated billing', () => {
    test('firm-wide WIP is grouped by entity and billed in one consolidated invoice', async ({ page }) => {
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

        // The WIP dashboard groups by billing entity
        await page.goto('/billing/wip');
        const acme = page.locator('[data-testid^="wip-entity-"]', {
            has: page.getByRole('heading', { name: 'Acme Industries Ltd' }),
        });
        await expect(acme.getByText('£1,484.91')).toBeVisible(); // 1,224.91 + 260.00
        await expect(acme.getByRole('link', { name: 'P-2021-0001' })).toBeVisible();
        await expect(acme.getByRole('link', { name: 'P-2021-0002' })).toBeVisible();

        // NovaTech's raised stage payment shows in its own EUR group
        await expect(page.getByRole('heading', { name: 'NovaTech GmbH' })).toBeVisible();
        await expect(page.getByText('€1,200.00').first()).toBeVisible();

        // One consolidated bill for everything Acme GB owes
        await acme.getByRole('button', { name: 'Draft Invoice (all)' }).click();

        await expect(
            page.getByText('Consolidated draft created — 4 line(s) across 2 matter(s), GBP 1,781.89.')
        ).toBeVisible();
        await expect(page.getByText('Consolidated — 2 matter(s)')).toBeVisible();

        // Lines are grouped under their matters
        await expect(page.getByRole('link', { name: 'P-2021-0001' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'P-2021-0002' })).toBeVisible();
        await expect(page.getByText('£1,781.89').first()).toBeVisible();

        // Billed WIP has left the dashboard; NovaTech remains
        await page.goto('/billing/wip');
        await expect(page.getByRole('heading', { name: 'Acme Industries Ltd' })).toBeHidden();
        await expect(page.getByRole('heading', { name: 'NovaTech GmbH' })).toBeVisible();
    });
});
