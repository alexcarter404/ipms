import { expect, test } from '@playwright/test';

// The portal has its own guard — start from a clean, logged-out context
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Client portal', () => {
    test('a client signs in, sees their portfolio and instructs a renewal', async ({ page }) => {
        await page.goto('/portal/login');
        await page.getByLabel('Email').fill('sarah.bennett@acme.example');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();

        // Their own matters, nothing else
        await expect(page.getByRole('heading', { name: 'Acme Industries Ltd' })).toBeVisible();
        const matters = page.locator('[data-testid="portal-matters"]');
        await expect(matters.getByText('P-2021-0001')).toBeVisible();
        await expect(matters.getByText('P-2021-0003')).toBeVisible();
        await expect(matters.getByText('TM-2023-0001')).toHaveCount(0); // NovaTech's

        // Renewal instruction: take the furthest-out renewal and let it lapse…
        const renewals = page.locator('[data-testid="portal-renewals"] tbody tr');
        const last = renewals.last();
        const reference = (await last.locator('td').first().innerText()).trim().split(/\s+/)[0];
        await last.getByRole('button', { name: 'Let Lapse' }).click();
        await expect(page.getByText(`Noted — ${reference} will be allowed to lapse.`)).toBeVisible();

        // …then instruct payment on the new furthest-out one
        const nextLast = page.locator('[data-testid="portal-renewals"] tbody tr').last();
        const payReference = (await nextLast.locator('td').first().innerText()).trim().split(/\s+/)[0];
        await nextLast.getByRole('button', { name: 'Instruct Payment' }).click();
        await expect(
            page.getByText(`Payment instructed for ${payReference} — the firm will handle it.`)
        ).toBeVisible();

        // Documents and invoices are visible; a document downloads
        const documents = page.locator('[data-testid="portal-documents"]');
        await expect(documents.getByText('Specification as filed')).toBeVisible();
        const downloadPromise = page.waitForEvent('download');
        await documents
            .locator('li', { hasText: 'Specification as filed' })
            .getByRole('link', { name: 'Download' })
            .click();
        expect((await downloadPromise).suggestedFilename()).toBe('specification-as-filed.pdf');

        // Invoice scoping: NovaTech's seeded INV-2026-0001 never shows for Acme
        const invoices = page.locator('[data-testid="portal-invoices"]');
        await expect(invoices.getByRole('heading', { name: 'Invoices' })).toBeVisible();
        await expect(invoices.getByText('INV-2026-0001')).toHaveCount(0);

        // Sign out lands back on the portal login
        await page.getByRole('button', { name: 'Sign out' }).click();
        await expect(page.getByText('Client portal — sign in to view your portfolio')).toBeVisible();
    });

    test('the firm system is out of the portal session reach', async ({ page }) => {
        await page.goto('/portal/login');
        await page.getByLabel('Email').fill('sarah.bennett@acme.example');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await expect(page.getByRole('heading', { name: 'Acme Industries Ltd' })).toBeVisible();

        // The firm's system asks for a firm login
        await page.goto('/matters');
        await expect(page).toHaveURL(/\/login$/);
    });
});
