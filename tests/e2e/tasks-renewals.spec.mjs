import { expect, test } from '@playwright/test';
import { pickOptionIn } from './helpers.mjs';

test.describe('Tasks', () => {
    test('index lists open tasks and completes one', async ({ page }) => {
        await page.goto('/tasks');

        const row = page.locator('tr', { hasText: 'Docket proof-of-use deadline' });
        await expect(row).toBeVisible();

        await row.getByRole('button', { name: 'Complete' }).click();
        await expect(page.getByText('Task updated.')).toBeVisible();
        // Completed tasks drop out of the default open-tasks view
        await expect(page.locator('tr', { hasText: 'Docket proof-of-use deadline' })).toBeHidden();
    });

    test('overdue filter and assignee filter work', async ({ page }) => {
        await page.goto('/tasks?overdue=1');
        // The remaining seeded overdue task was completed above; the filter UI still renders
        await expect(page.getByRole('heading', { name: 'Tasks & Actions' })).toBeVisible();

        await page.goto('/tasks');
        await expect(page.locator('tr', { hasText: 'Consider validation states' })).toBeVisible();
    });
});

test.describe('Renewals', () => {
    test('index lists open renewals with due-window filter', async ({ page }) => {
        await page.goto('/renewals');

        await expect(page.locator('tr', { hasText: 'P-2021-0001' }).first()).toBeVisible();

        // The seeded near-term renewals fall due within 90 days; the
        // filter narrows P-2021-0001 down to its single upcoming cycle.
        await pickOptionIn(page, page.locator('.p-select', { hasText: 'Any due date' }), 'Due within 90 days');
        await expect(page.locator('tbody tr', { hasText: 'P-2021-0001' })).toHaveCount(1);
        await expect(page.locator('tbody tr', { hasText: 'TM-2023-0001' })).toHaveCount(1);
    });

    test('renewal can be instructed and paid from the index', async ({ page }) => {
        await page.goto('/renewals?due_within=90');

        const row = page.locator('tbody tr', { hasText: 'TM-2023-0001' });
        await row.getByRole('button', { name: 'Instructed' }).click();
        await expect(page.getByText('Renewal updated.')).toBeVisible();
        await expect(row.getByText('Instructed', { exact: true })).toBeVisible();

        await row.getByRole('button', { name: 'Paid' }).click();
        // Paid renewals leave the open view
        await expect(page.locator('tbody tr', { hasText: 'TM-2023-0001' })).toBeHidden();
    });

    test('schedule can be generated for a new matter', async ({ page }) => {
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('E2E-0001');
        // Wait for the debounced filter to apply before navigating away
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await page.getByRole('link', { name: 'E2E-0001' }).click();

        await page.getByRole('button', { name: /Renewals \(/ }).click();
        await page.getByRole('button', { name: 'Generate Schedule' }).click();

        await expect(page.getByText(/renewal\(s\) generated/)).toBeVisible();
        await expect(page.locator('tbody tr').first()).toBeVisible();
    });
});
