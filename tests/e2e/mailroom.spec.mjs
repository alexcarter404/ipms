import { expect, test } from '@playwright/test';
import { pickOptionIn } from './helpers.mjs';

test.describe('Mailroom', () => {
    test('matched mail files itself on the matter with its attachment', async ({ page }) => {
        await page.goto('/mailroom');

        // Seeded: the examination report matched P-2021-0002 by its reference
        const matched = page.locator('tbody tr', { hasText: 'examination report enclosed' });
        await expect(matched).toContainText('Ricardo Mendez');
        await expect(matched.getByRole('link', { name: 'P-2021-0002' })).toBeVisible();

        // The matter carries the inbound comm and the filed attachment
        await matched.getByRole('link', { name: 'P-2021-0002' }).click();
        await page.getByRole('tab', { name: /Comms \(1\)/ }).click();
        await expect(page.getByText('Inbound', { exact: true })).toBeVisible();
        await expect(page.getByText('from Ricardo Mendez')).toBeVisible();

        await page.getByRole('tab', { name: /Documents \(1\)/ }).click();
        const doc = page.locator('[data-testid="documents-panel"] tr', { hasText: 'examination-report' });
        await expect(doc).toContainText('email');
        await expect(doc).toContainText('Correspondence');
    });

    test('unmatched mail is reviewed and filed by hand', async ({ page }) => {
        await page.goto('/mailroom');

        const unmatched = page.locator('tbody tr', { hasText: 'Purchase order update' });
        await expect(unmatched).toContainText('Unmatched');
        await unmatched.getByRole('button', { name: 'View →' }).click();

        const detail = page.locator('[data-testid="email-detail"]');
        await expect(detail).toContainText('PO-IP-2027');
        await pickOptionIn(page, detail.locator('.p-select'), 'P-2021-0001');
        await detail.getByRole('button', { name: 'File', exact: true }).click();
        await expect(
            page.getByText('Email filed on P-2021-0001 — attachments added to its documents.')
        ).toBeVisible();

        await expect(page.locator('tbody tr', { hasText: 'Purchase order update' })).not.toContainText('Unmatched');

        // Checking an empty mailbox is a clean no-op
        await page.getByRole('button', { name: 'Check Mailbox Now' }).click();
        await expect(page.getByText('Checked the mailbox — 0 new email(s), 0 matched.')).toBeVisible();
    });
});
