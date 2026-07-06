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
        await expect(amendment.getByRole('button', { name: '⟲ Restore this state' })).toBeVisible();

        // Creation events flow in from the matter's children too
        await expect(trail.locator('li', { hasText: 'Budget' }).first()).toContainText('created');
    });

    test('an edit is audited and any captured state can be restored', async ({ page }) => {
        await openMatter(page, 'P-2021-0001');

        // Amend the title twice, leaving two captured states in the trail
        await page.getByRole('link', { name: 'Edit', exact: true }).click();
        await field(page, 'Title').fill('Self-sealing valve assembly Mk II');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.getByText('Matter updated.')).toBeVisible();
        await page.getByRole('link', { name: 'Edit', exact: true }).click();
        await field(page, 'Title').fill('Self-sealing valve assembly Mk III');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.getByText('Matter updated.')).toBeVisible();
        await expect(page.getByText('Self-sealing valve assembly Mk III')).toBeVisible();

        // Both edits sit at the top of the history with their diffs
        await page.getByRole('tab', { name: 'History' }).click();
        const entries = page.locator('[data-testid="audit-trail"] li');
        await expect(entries.first()).toContainText('Self-sealing valve assembly Mk III');
        await expect(entries.first()).toContainText('Alex Carter');

        // Restore the state the first edit produced (Mk II)
        const mkTwo = entries.nth(1);
        await expect(mkTwo).toContainText('Self-sealing valve assembly Mk II');
        await mkTwo.getByRole('button', { name: '⟲ Restore this state' }).click();
        await page.locator('.p-confirmdialog').getByRole('button', { name: 'Restore' }).click();
        await expect(
            page.getByText('State restored — the record now carries the values from this entry.')
        ).toBeVisible();
        await expect(page.getByRole('banner').getByText('Self-sealing valve assembly Mk II')).toBeVisible();

        // The matter's created entry captures its original values — restore those
        await page.getByRole('tab', { name: 'History' }).click();
        const created = page
            .locator('[data-testid="audit-trail"] li', { hasText: 'Matter — P-2021-0001' })
            .filter({ hasText: 'Created' })
            .first();
        await created.getByRole('button', { name: '⟲ Restore this state' }).click();
        await page.locator('.p-confirmdialog').getByRole('button', { name: 'Restore' }).click();
        await expect(
            page.getByText('State restored — the record now carries the values from this entry.').first()
        ).toBeVisible();
        await expect(
            page.getByRole('banner').getByText('Self-sealing valve assembly', { exact: true })
        ).toBeVisible();
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
