import { expect, test } from '@playwright/test';
import { field } from './helpers.mjs';

const openMatter = async (page, reference) => {
    await page.goto('/matters');
    await page.getByPlaceholder('Search ref, title, number, client…').fill(reference);
    await expect(page.locator('tbody tr')).toHaveCount(1); // debounced filter settled
    await page.getByRole('link', { name: reference }).click();
};

test.describe('Audit history', () => {
    test('the matter history timeline shows seeded activity with attribution', async ({ page }) => {
        await openMatter(page, 'P-2021-0001');
        await page.getByRole('tab', { name: 'History' }).click();

        const trail = page.locator('[data-testid="audit-trail"]');
        // The seeded description amendment, attributed to the attorney
        const amendment = trail.locator('li', { hasText: 'Jordan Reeves' }).first();
        await expect(amendment).toContainText('Matter — P-2021-0001');
        await expect(amendment).toContainText('description');
        await expect(amendment.getByRole('button', { name: '⟲ Roll back' })).toBeVisible();

        // Creation events flow in from the matter's children too
        await expect(trail.locator('li', { hasText: 'Budget' }).first()).toContainText('created');
    });

    test('an edit is audited and can be rolled back to the previous state', async ({ page }) => {
        await openMatter(page, 'P-2021-0001');

        // Amend the title
        await page.getByRole('link', { name: 'Edit', exact: true }).click();
        await field(page, 'Title').fill('Self-sealing valve assembly Mk II');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.getByText('Matter updated.')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'P-2021-0001' })).toBeVisible();
        await expect(page.getByText('Self-sealing valve assembly Mk II')).toBeVisible();

        // The change sits at the top of the history with its before/after
        await page.getByRole('tab', { name: 'History' }).click();
        const entry = page.locator('[data-testid="audit-trail"] li').first();
        await expect(entry).toContainText('updated');
        await expect(entry).toContainText('Alex Carter');
        await expect(entry).toContainText('Self-sealing valve assembly Mk II');

        // Time-travel back to the pre-change state
        await entry.getByRole('button', { name: '⟲ Roll back' }).click();
        await page
            .locator('.p-confirmdialog')
            .getByRole('button', { name: 'Roll back' })
            .click();
        await expect(
            page.getByText('Rolled back — the record now carries the values from before this change.')
        ).toBeVisible();

        // The reload lands back on Overview with the original title restored
        await expect(page.getByText('Self-sealing valve assembly', { exact: true }).first()).toBeVisible();

        // ...and the rollback itself was audited as the newest entry
        await page.getByRole('tab', { name: 'History' }).click();
        const newest = page.locator('[data-testid="audit-trail"] li').first();
        await expect(newest).toContainText('updated');
        await expect(newest).toContainText('Self-sealing valve assembly');
    });

    test('the client screen shows its audit history including entities', async ({ page }) => {
        await page.goto('/clients');
        await page.getByRole('link', { name: 'ACME' }).click();

        await expect(page.getByRole('heading', { name: 'Audit history' })).toBeVisible();
        const trail = page.locator('[data-testid="audit-trail"]');
        await expect(trail.locator('li', { hasText: 'Client — Acme Industries Ltd' }).first())
            .toContainText('created');
        await expect(trail.locator('li', { hasText: 'Entity' }).first()).toContainText('Alex Carter');
    });
});
