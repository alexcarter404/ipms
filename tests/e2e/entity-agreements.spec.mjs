import { expect, test } from '@playwright/test';
import { field, pickOptionIn } from './helpers.mjs';

test.describe('Entity-level fee agreements', () => {
    test('entity defaults cascade to matters and can be overridden per case', async ({ page }) => {
        // The client screen shows each entity's default agreement
        await page.goto('/clients');
        await page.getByRole('link', { name: 'ACME', exact: true }).click();
        const usEntity = page.locator('li', { hasText: 'Acme Industries Inc' });
        await expect(usEntity.getByText('Fee agreement:')).toBeVisible();
        await expect(usEntity.getByText('Blended Hourly')).toBeVisible();

        // P-2021-0003 bills to the US entity — it inherits the blended default
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0003');
        await page.getByRole('link', { name: 'P-2021-0003' }).click();
        await page.getByRole('tab', { name: 'Billing' }).click();

        await expect(page.getByText('Inherited from entity')).toBeVisible();
        await expect(page.getByText('Blended Hourly')).toBeVisible();

        // Time is valued at the entity's blended rate, in its USD currency
        await page.getByRole('button', { name: 'Log Time' }).click();
        const dialog = page.locator('.p-dialog');
        await pickOptionIn(page, dialog.locator('.p-select').first(), 'Jordan Reeves');
        await field(dialog, 'Minutes worked').fill('60');
        await dialog.getByRole('button', { name: 'Log Time' }).click();
        await expect(page.getByText('Time logged: 60m billed as 60m (USD 300.00).')).toBeVisible();

        // Case-level override: switch this matter to plain hourly
        await page.getByRole('button', { name: 'Override for Matter' }).click();
        await pickOptionIn(page, dialog.locator('.p-select').first(), /^Hourly$/);
        await dialog.getByRole('button', { name: 'Save Agreement' }).click();
        await expect(page.getByText('Billing agreement saved.')).toBeVisible();
        await expect(page.getByText('Inherited from entity')).toBeHidden();

        // And back again — removing the override restores the entity default
        await page.getByRole('button', { name: 'Remove override' }).click();
        await expect(page.getByText('Override removed — the entity default applies.')).toBeVisible();
        await expect(page.getByText('Inherited from entity')).toBeVisible();
        await expect(page.getByText('Blended Hourly')).toBeVisible();
    });
});
