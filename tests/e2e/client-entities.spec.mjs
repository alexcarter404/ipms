import { expect, test } from '@playwright/test';
import { field, pickOption, pickOptionIn } from './helpers.mjs';

test.describe('Client entities', () => {
    test('client page lists group entities with billing details', async ({ page }) => {
        await page.goto('/clients');
        await page.getByRole('link', { name: 'ACME' }).click();

        await expect(page.getByRole('heading', { name: 'Entities' })).toBeVisible();
        // Two seeded entities: GB default + US
        await expect(page.getByText('Acme Industries Ltd').nth(1)).toBeVisible();
        await expect(page.getByText('Acme Industries Inc').first()).toBeVisible();
        await expect(page.getByText('Default', { exact: true })).toBeVisible();
        await expect(page.getByText(/us-invoices@acme\.example/).first()).toBeVisible();
        await expect(page.getByText(/ref PO-IP-2026/).first()).toBeVisible();
    });

    test('an entity can be added with its own billing destination', async ({ page }) => {
        await page.goto('/clients');
        await page.getByRole('link', { name: 'NOVA' }).click();

        await page.getByRole('button', { name: 'Add entity' }).click();
        await field(page, 'Entity name *').fill('NovaTech Austria GmbH');
        await pickOption(page, page, 'Country', 'AT — Austria');
        await field(page, 'Billing email').fill('rechnungen@novatech.example');
        await field(page, 'Billing reference / PO').fill('AT-2026');
        await page.getByRole('button', { name: 'Add Entity' }).click();

        await expect(page.getByText('Entity added.')).toBeVisible();
        await expect(page.getByText('NovaTech Austria GmbH').first()).toBeVisible();
        await expect(page.getByText(/rechnungen@novatech\.example/).first()).toBeVisible();
    });

    test('matter overview names its billing entity, explicit or fallback', async ({ page }) => {
        // P-2021-0003 is explicitly billed to the US entity
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0003');
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'P-2021-0003' }).click();

        await expect(page.getByText('Billing entity')).toBeVisible();
        await expect(page.getByText('Acme Industries Inc').first()).toBeVisible();

        // P-2021-0001 falls back to the client default
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0001');
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'P-2021-0001' }).click();

        await expect(page.getByText(/Acme Industries Ltd \(client default\)/)).toBeVisible();
    });

    test('matter form offers the entities of the selected client', async ({ page }) => {
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0002');
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'P-2021-0002' }).click();
        await page.getByRole('link', { name: 'Edit', exact: true }).click();

        const entitySelect = field(page, 'Billing entity', 'select');
        await entitySelect.click();
        const overlay = page.locator('.p-select-overlay');
        await expect(overlay.getByRole('option', { name: /Acme Industries Ltd \(default\)/ })).toHaveCount(1);
        await overlay.getByRole('option', { name: 'Acme Industries Inc' }).click();
        await page.getByRole('button', { name: 'Save Changes' }).click();

        await expect(page.getByText('Matter updated.')).toBeVisible();
        await expect(page.getByText('Acme Industries Inc').first()).toBeVisible();
    });
});
