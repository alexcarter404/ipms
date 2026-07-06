import { expect, test } from '@playwright/test';
import { pickOptionIn } from './helpers.mjs';

test.describe('IPO integrations', () => {
    test('the inbox shows office messages with automation audit trails', async ({ page }) => {
        await page.goto('/integrations');

        // Seeded: an auto-processed USPTO office action
        await expect(page.locator('tr', { hasText: 'Non-final Office Action' })).toBeVisible();
        await page.locator('tr', { hasText: 'Non-final Office Action' }).getByRole('button', { name: 'Review →' }).click();

        const detail = page.locator('[data-testid="message-detail"]');
        await expect(detail.getByText('Automated actions')).toBeVisible();
        await expect(detail.getByText('Applied workflow “Office Action Response” — 3 task(s)', { exact: false })).toBeVisible();
        await expect(detail.getByText('Drafted communication “Office Action Report” for review')).toBeVisible();
        await page.keyboard.press('Escape');

        // The automation's artefacts are really on the matter
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0003');
        await expect(page.locator('tbody tr')).toHaveCount(1); // debounced filter settled
        await page.getByRole('link', { name: 'P-2021-0003' }).click();
        await expect(page.getByRole('banner').getByText('Office Action')).toBeVisible();
        await page.getByRole('tab', { name: /Tasks \(3\)/ }).click();
        await expect(page.locator('tr', { hasText: 'File response' })).toBeVisible();
        await page.getByRole('tab', { name: /Comms \(1\)/ }).click();
        await expect(page.getByText('Official communication received')).toBeVisible();
    });

    test('a matched grant is processed by hand: fields, fees and status flow through', async ({ page }) => {
        await page.goto('/integrations');

        await page.locator('tr', { hasText: 'Decision to grant' }).getByRole('button', { name: 'Review →' }).click();
        const detail = page.locator('[data-testid="message-detail"]');
        await expect(detail.getByText('EP3456789').first()).toBeVisible();
        await detail.getByRole('button', { name: 'Process Message' }).click();

        await expect(page.getByText(/Processed — \d+ action\(s\) applied to P-2021-0002\./)).toBeVisible();

        // The matter now carries the grant + the official fee as WIP
        await page.goto('/matters');
        await page.getByPlaceholder('Search ref, title, number, client…').fill('P-2021-0002');
        await expect(page.locator('tbody tr')).toHaveCount(1); // debounced filter settled
        await page.getByRole('link', { name: 'P-2021-0002' }).click();
        await expect(page.getByRole('banner').getByText('Granted')).toBeVisible();
        await expect(page.getByText('EP3456789')).toBeVisible();
        await page.getByRole('tab', { name: 'Billing' }).click();
        await expect(page.locator('tr', { hasText: 'Grant and publishing fee' })).toContainText('€960.00');
    });

    test('an unmatched message is assigned to a matter and processed', async ({ page }) => {
        await page.goto('/integrations');

        await expect(page.locator('tr', { hasText: 'Publication of the application' })).toContainText('Unmatched');
        await page.locator('tr', { hasText: 'Publication of the application' }).getByRole('button', { name: 'Review →' }).click();

        const detail = page.locator('[data-testid="message-detail"]');
        await pickOptionIn(page, detail.locator('.p-select'), 'P-2021-0003');
        await detail.getByRole('button', { name: 'Assign', exact: true }).click();
        await expect(page.getByText('Message assigned — ready to process.')).toBeVisible();

        await page.locator('tr', { hasText: 'Publication of the application' }).getByRole('button', { name: 'Review →' }).click();
        await detail.getByRole('button', { name: 'Process Message' }).click();
        await expect(page.getByText(/Processed — \d+ action\(s\) applied to P-2021-0003\./)).toBeVisible();

        // Polling with an empty exchange is a clean no-op
        await page.getByRole('button', { name: 'Poll offices now' }).click();
        await expect(page.getByText(/Polled all offices — 0 new message\(s\)/)).toBeVisible();
    });

    test('outbound submissions are drafted, packaged and pushed to the office', async ({ page }) => {
        await page.goto('/integrations');
        const outbound = page.locator('[data-testid="submissions"]');

        // Seeded: an acknowledged renewal payment carrying the office receipt
        await expect(outbound.locator('tr', { hasText: 'Renewal Payment' })).toContainText('UKIPO-RCPT-4471');

        // The draft OA response was packaged from the matter's data
        const draftRow = outbound.locator('tr', { hasText: 'Office Action Response' });
        await expect(draftRow).toContainText('P-2021-0003');
        await expect(draftRow).toContainText('File response');
        await draftRow.getByRole('button', { name: 'View' }).click();
        const detail = page.locator('[data-testid="submission-detail"]');
        await expect(detail.getByText('"application_no": "17/456,789"')).toBeVisible();

        // Push it through the file-drop exchange
        await detail.getByRole('button', { name: 'Submit to Office' }).click();
        await expect(
            page.getByText('Submitted to the exchange outbox — awaiting the office receipt.')
        ).toBeVisible();
        await expect(draftRow.getByText('Submitted')).toBeVisible();

        // Draft a fresh document submission from the modal, then delete it
        await outbound.getByRole('button', { name: 'New Submission' }).click();
        const modal = page.locator('.p-dialog');
        await pickOptionIn(page, modal.locator('.p-select').first(), 'European Patent Office');
        await pickOptionIn(page, modal.locator('.p-select').nth(1), 'Document / Form');
        await pickOptionIn(page, modal.locator('.p-select').nth(2), 'P-2021-0001');
        await modal.getByRole('textbox').fill('Certified priority document');
        await modal.getByRole('button', { name: 'Create Draft' }).click();
        await expect(page.getByText(/Submission draft created — Document \/ Form to European Patent Office for P-2021-0001\./)).toBeVisible();

        const newRow = outbound.locator('tr', { hasText: 'Document / Form' });
        await expect(newRow.getByText('Draft')).toBeVisible();
        await newRow.getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByText('Submission draft deleted.')).toBeVisible();
    });
});
