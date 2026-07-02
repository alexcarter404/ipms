import { expect, test } from '@playwright/test';
import { field } from './helpers.mjs';

test.describe('Clients', () => {
    test('index lists seeded clients and searches', async ({ page }) => {
        await page.goto('/clients');

        await expect(page.getByRole('link', { name: 'ACME' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'NOVA' })).toBeVisible();

        await page.getByPlaceholder('Search name or code…').fill('NovaTech');
        await expect(page.getByRole('link', { name: 'NOVA' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'ACME' })).toBeHidden();
    });

    test('client detail shows contacts and matters', async ({ page }) => {
        await page.goto('/clients');
        await page.getByRole('link', { name: 'ACME' }).click();

        await expect(page.getByRole('heading', { name: /Acme Industries Ltd/ })).toBeVisible();
        await expect(page.getByText('Sarah Bennett')).toBeVisible();
        await expect(page.getByRole('link', { name: 'P-2021-0001' })).toBeVisible();
    });

    test('client and contact can be created', async ({ page }) => {
        await page.goto('/clients/create');

        await field(page, 'Code').fill('E2EC');
        await field(page, 'Name').fill('E2E Testing Corp');
        await field(page, 'Type', 'select').selectOption('company');
        await field(page, 'Email').fill('legal@e2e.example');
        await page.getByRole('button', { name: 'Create Client' }).click();

        await expect(page.getByRole('heading', { name: /E2E Testing Corp/ })).toBeVisible();

        // Add a contact inline
        await page.getByRole('button', { name: 'Add contact' }).click();
        await field(page, 'Name *').fill('Pat Tester');
        await field(page, 'Email').fill('pat@e2e.example');
        await page.getByRole('button', { name: 'Save Contact' }).click();

        await expect(page.getByText('Pat Tester')).toBeVisible();
    });
});
