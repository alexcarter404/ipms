import { expect, test } from '@playwright/test';
import { field, pickOption } from './helpers.mjs';

test.describe('Matter take-on', () => {
    test('stage contract is shown and enforced, tasks start at the entry stage', async ({ page }) => {
        await page.goto('/matters');
        await page.getByRole('link', { name: 'Take-On' }).click();

        await expect(page.getByRole('heading', { name: 'Matter Take-On' })).toBeVisible();

        await pickOption(page, page, 'Workflow *', 'Patent Filing Formalities');
        await pickOption(page, page, 'Enter at stage', '3. Request examination');

        // The cumulative contract for stages 1-3 appears as a checklist
        const contract = page.locator('[data-testid="stage-contract"]');
        await expect(contract).toBeVisible();
        await expect(contract).toContainText('Data contract for entering at “Request examination”');
        for (const item of [
            'Application number',
            'Application (filing) date',
            'Priority number',
            'Priority date',
            'Responsible attorney',
        ]) {
            await expect(contract.getByText(item)).toBeVisible();
        }
        await expect(contract).toContainText('2 task(s) will be created, starting with “Request examination”');

        // Checklist items tick off as the data is captured
        await field(page, 'Application no').fill('GB2612345.6');
        await expect(
            contract.locator('li', { hasText: 'Application number' })
        ).toContainText('✓');

        // Complete the matter details + the rest of the contract
        await field(page, 'Reference').fill('E2E-TO-0001');
        await field(page, 'Title').fill('Taken-on valve improvement');
        await pickOption(page, page, 'Jurisdiction', 'GB — United Kingdom');
        await field(page, 'Client', 'select').click();
        await page.locator('.p-select-overlay [role="option"]').first().click();
        await field(page, 'Application date').fill('2026-01-15');
        await field(page, 'Application date').press('Tab');
        await field(page, 'Priority no').fill('GB2512345.1');
        await field(page, 'Priority date').fill('2025-01-15');
        await field(page, 'Priority date').press('Tab');
        await field(page, 'Responsible attorney', 'select').click();
        await page.locator('.p-select-overlay [role="option"]').first().click();

        await page.getByRole('button', { name: 'Take On Matter' }).click();

        await expect(page.getByRole('heading', { name: 'E2E-TO-0001' })).toBeVisible();
        await expect(
            page.getByText('Matter taken on — 2 task(s) created from the entry stage onward.')
        ).toBeVisible();

        // Only the entry stage onward became tasks
        await page.getByRole('tab', { name: /Tasks \(2\)/ }).click();
        await expect(page.locator('tr', { hasText: 'Request examination' })).toBeVisible();
        await expect(page.locator('tr', { hasText: 'Foreign filing decision' })).toBeVisible();
        await expect(page.locator('tr', { hasText: 'Report filing to client' })).toBeHidden();
        await expect(page.locator('tr', { hasText: 'File priority documents' })).toBeHidden();
    });

    test('submitting without the contract shows stage-specific errors', async ({ page }) => {
        await page.goto('/matters/take-on');

        await pickOption(page, page, 'Workflow *', 'Patent Filing Formalities');
        await pickOption(page, page, 'Enter at stage', '2. File priority documents');

        await field(page, 'Reference').fill('E2E-TO-0002');
        await field(page, 'Title').fill('Missing contract data');
        await pickOption(page, page, 'Jurisdiction', 'GB — United Kingdom');
        await field(page, 'Client', 'select').click();
        await page.locator('.p-select-overlay [role="option"]').first().click();

        await page.getByRole('button', { name: 'Take On Matter' }).click();

        await expect(
            page.getByText('The application no is required to take the matter on at this stage.')
        ).toBeVisible();
        await expect(
            page.getByText('The priority date is required to take the matter on at this stage.')
        ).toBeVisible();
    });

    test('the builder saves a data contract on a step', async ({ page }) => {
        await page.goto('/workflows/create');

        await field(page, 'Name *').fill('E2E Contracted Workflow');
        await pickOption(page, page, 'Trigger event', 'Grant Date');

        await page.getByRole('button', { name: 'Add Step' }).click();
        await field(page, 'Title *').fill('Record grant details');
        await field(page, 'Offset *').fill('1');

        await page.locator('.p-multiselect').click();
        const overlay = page.locator('.p-multiselect-overlay');
        await overlay.getByRole('option', { name: 'Registration / grant number' }).click();
        await overlay.getByRole('option', { name: 'Registration / grant date' }).click();
        await page.keyboard.press('Escape');

        await page.getByRole('button', { name: 'Create Workflow' }).click();
        await expect(page.getByText('Workflow created.')).toBeVisible();

        // The edit page reloads the saved step with its contract chips
        await expect(
            page.locator('.p-multiselect').getByText('Registration / grant number')
        ).toBeVisible();
        await expect(
            page.locator('.p-multiselect').getByText('Registration / grant date')
        ).toBeVisible();
    });
});
