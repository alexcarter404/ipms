import { expect, test } from '@playwright/test';
import { pickOptionIn } from './helpers.mjs';

test.describe('Register import & reconciliation', () => {
    test('drift against the office register is reviewed and accepted', async ({ page }) => {
        await page.goto('/integrations');

        const checks = page.locator('[data-testid="register-checks"]');
        // Seeded: the EPO register knows a publication our docket missed
        const drifted = checks.locator('div.px-4.py-3', { hasText: 'P-2021-0002' });
        await expect(drifted).toContainText('publication no');
        await expect(drifted).toContainText('EP4123456');

        await drifted.getByRole('button', { name: 'Accept office values' }).click();
        await expect(page.getByText('Applied the register values to P-2021-0002.')).toBeVisible();

        // Re-running now comes back clean
        await checks.getByRole('button', { name: 'Reconcile Now' }).click();
        await expect(page.getByText(/Reconciled \d+ matter\(s\) against the registers — 0 drifted\./)).toBeVisible();
        await expect(checks.getByText('No open register checks — the docket matches the offices.')).toBeVisible();

        // The matter now carries the register's publication number
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0002');
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'P-2021-0002' }).click();
        await expect(page.getByText('EP4123456')).toBeVisible();
    });

    test('a matter is imported straight from the office register', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('button', { name: 'Import from Office' }).click();

        const modal = page.locator('.p-dialog');
        await pickOptionIn(page, modal.locator('.p-select').first(), 'European Patent Office');
        await modal.getByPlaceholder('e.g. EP24555001.1').fill('EP24555001.1');
        await pickOptionIn(page, modal.locator('.p-select').nth(1), 'NovaTech GmbH');
        await modal.getByRole('button', { name: 'Import Matter' }).click();

        // Straight onto the freshly created matter, filled from the register
        await expect(page.getByText(/Imported P-\d{4}-\d{4} from the epo register\./)).toBeVisible();
        await expect(page.getByText('Adaptive haptic feedback allocator').first()).toBeVisible();
        await expect(page.getByText('EP24555001.1')).toBeVisible();
        await expect(page.getByText('EP4477001')).toBeVisible();
        await expect(page.getByRole('banner').getByText('Under Examination')).toBeVisible();

        // Unknown numbers are refused
        await page.goto('/matters');
        await page.getByRole('button', { name: 'Import from Office' }).click();
        await pickOptionIn(page, modal.locator('.p-select').first(), 'European Patent Office');
        await modal.getByPlaceholder('e.g. EP24555001.1').fill('EP00000000.0');
        await pickOptionIn(page, modal.locator('.p-select').nth(1), 'NovaTech GmbH');
        await modal.getByRole('button', { name: 'Import Matter' }).click();
        await expect(page.getByText('The epo register has no record of EP00000000.0 — check the number.')).toBeVisible();
    });
});
