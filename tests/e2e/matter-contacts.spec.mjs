import { expect, test } from '@playwright/test';
import { field } from './helpers.mjs';

test.describe('Matter contacts', () => {
    test('contacts tab lists linked contacts with roles and types', async ({ page }) => {
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0001');
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'P-2021-0001' }).click();

        // Overview surfaces main contact + docketing email
        await expect(page.getByText('Main contact')).toBeVisible();
        await expect(page.getByText('Sarah Bennett')).toBeVisible();
        await expect(page.getByText('ip-docketing@acme.example')).toBeVisible();

        await page.getByRole('button', { name: /Contacts \(/ }).click();
        const docketingRow = page.locator('tr', { hasText: 'Acme IP Docketing' });
        await expect(docketingRow.getByText('Mailbox / Docketing')).toBeVisible();
        await expect(docketingRow.getByText('Docketing', { exact: true })).toBeVisible();
        await expect(page.locator('tr', { hasText: 'Sarah Bennett' }).getByText('Main Contact')).toBeVisible();
    });

    test('a billing mailbox can be created and linked from the matter', async ({ page }) => {
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('TM-2023-0001');
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'TM-2023-0001' }).click();

        await page.getByRole('button', { name: /Contacts \(/ }).click();
        await page.getByRole('radio', { name: 'New contact' }).check();
        await field(page, 'Name').fill('NovaTech Invoices');
        await field(page, 'Contact type', 'select').selectOption('mailbox');
        await field(page, 'Email').fill('invoices@novatech.example');
        await field(page, 'Role on matter', 'select').selectOption('billing');
        await page.getByRole('button', { name: 'Link', exact: true }).click();

        await expect(page.getByText('Contact linked.')).toBeVisible();
        const row = page.locator('tr', { hasText: 'NovaTech Invoices' });
        await expect(row.getByText('Billing')).toBeVisible();
        await expect(row.getByText('invoices@novatech.example')).toBeVisible();
    });

    test('composer prefills the main contact and can switch to docketing', async ({ page }) => {
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0001');
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'P-2021-0001' }).click();

        await page.getByRole('button', { name: /Comms \(/ }).click();
        await page.getByRole('button', { name: 'Compose' }).click();

        const modal = page.locator('div').filter({ hasText: 'Compose Communication' }).last();
        // Prefilled with the main contact
        await expect(field(modal, 'Recipient name')).toHaveValue('Sarah Bennett');
        await expect(field(modal, 'Recipient email')).toHaveValue('sarah.bennett@acme.example');

        // Switching to the docketing mailbox updates the recipient
        await field(modal, 'Send to matter contact', 'select').selectOption({
            label: 'Acme IP Docketing (docketing) — ip-docketing@acme.example',
        });
        await expect(field(modal, 'Recipient email')).toHaveValue('ip-docketing@acme.example');
    });

    test('client page distinguishes mailbox contacts', async ({ page }) => {
        await page.goto('/clients');
        await page.getByRole('link', { name: 'ACME' }).click();

        const contacts = page.locator('li', { hasText: 'Acme IP Docketing' }).first();
        await expect(contacts.getByText('Mailbox / Docketing')).toBeVisible();
    });
});
