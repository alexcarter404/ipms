import { expect, test } from '@playwright/test';
import { field } from './helpers.mjs';

test.describe('Workflows', () => {
    test('index lists seeded workflows', async ({ page }) => {
        await page.goto('/workflows');

        await expect(page.getByRole('link', { name: 'Patent Filing Formalities' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Office Action Response' })).toBeVisible();
    });

    test('workflow with steps can be created in the builder', async ({ page }) => {
        await page.goto('/workflows/create');

        await field(page, 'Name *').fill('E2E Grant Formalities');
        await field(page, 'Trigger event', 'select').selectOption('grant');

        await page.getByRole('button', { name: 'Add Step' }).click();
        await field(page, 'Title *').fill('Pay grant fee');
        await field(page, 'Offset *').fill('2');
        await field(page, 'Unit', 'select').selectOption('months');

        await page.getByRole('button', { name: 'Create Workflow' }).click();

        await expect(page.getByText('Workflow created.')).toBeVisible();
        // Lands on the edit page with the saved step
        await expect(field(page, 'Title *')).toHaveValue('Pay grant fee');
    });

    test('applying a workflow to a matter creates its tasks', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('link', { name: 'P-2021-0002' }).click();

        await page.getByRole('button', { name: /Tasks \(/ }).click();
        await page.getByRole('button', { name: 'Apply Workflow' }).click();

        const modal = page.locator('div').filter({ hasText: 'Apply Workflow' }).last();
        await field(modal, 'Workflow', 'select').selectOption({ label: 'Patent Filing Formalities' });
        await expect(modal.getByText('Trigger: filing')).toBeVisible();
        await modal.getByRole('button', { name: 'Apply', exact: true }).click();

        await expect(page.getByText(/task\(s\) created/)).toBeVisible();
        await expect(page.locator('tr', { hasText: 'Request examination' })).toBeVisible();
        await expect(page.locator('tr', { hasText: 'Foreign filing decision' })).toBeVisible();
    });
});

test.describe('Communication templates', () => {
    test('index lists seeded templates and editor shows merge fields', async ({ page }) => {
        await page.goto('/templates');

        await expect(page.getByRole('link', { name: 'Filing Confirmation' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Renewal Reminder' })).toBeVisible();

        await page.getByRole('link', { name: 'Filing Confirmation' }).click();
        await expect(page.getByRole('heading', { name: 'Merge Fields' })).toBeVisible();
        await expect(page.getByText('{{matter.reference}}').first()).toBeVisible();
    });

    test('a communication is composed from a template with merge fields resolved', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('link', { name: 'P-2021-0001' }).click();

        await page.getByRole('button', { name: /Comms \(/ }).click();
        await page.getByRole('button', { name: 'Compose' }).click();

        const modal = page.locator('div').filter({ hasText: 'Compose Communication' }).last();
        await field(modal, 'From template', 'select').selectOption({ label: 'Filing Confirmation (email)' });
        await modal.getByRole('button', { name: 'Load' }).click();

        // Merge fields resolved against the matter by the preview endpoint
        await expect(field(modal, 'Subject')).toHaveValue(/P-2021-0001 — Application filed \(GB\)/);
        await expect(field(modal, 'Body', 'textarea')).toHaveValue(/GB2101234\.5/);

        await modal.getByRole('button', { name: 'Save Draft' }).click();
        await expect(page.getByText('Communication saved as draft.')).toBeVisible();

        // Draft appears in the log and can be marked sent
        const entry = page.locator('div.rounded-lg', { hasText: 'Application filed' }).first();
        await entry.getByRole('button', { name: 'Mark Sent' }).click();
        await expect(page.getByText('Communication marked as sent.')).toBeVisible();
    });

    test('new template can be created', async ({ page }) => {
        await page.goto('/templates/create');

        await field(page, 'Name *').fill('E2E Status Update');
        await field(page, 'Channel *', 'select').selectOption('email');
        await field(page, 'Subject').fill('{{matter.reference}} — status');
        await field(page, 'Body *', 'textarea').fill('Dear {{contact.name}}, status of {{matter.title}}: {{matter.status}}.');
        await page.getByRole('button', { name: 'Create Template' }).click();

        await expect(page.getByText('Template created.')).toBeVisible();
        await expect(page.getByRole('link', { name: 'E2E Status Update' })).toBeVisible();
    });
});
