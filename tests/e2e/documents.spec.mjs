import { expect, test } from '@playwright/test';
import { pickOptionIn } from './helpers.mjs';

const openMatter = async (page, reference) => {
    await page.goto('/matters');
    await page.getByPlaceholder('Search ref, title, number, client…').fill(reference);
    await expect(page.locator('tbody tr')).toHaveCount(1); // debounced filter settled
    await page.getByRole('link', { name: reference }).click();
};

test.describe('Documents', () => {
    test('office messages auto-file their documents on the docket', async ({ page }) => {
        await openMatter(page, 'P-2021-0003');
        await page.getByRole('tab', { name: /Documents \(1\)/ }).click();

        const row = page.locator('[data-testid="documents-panel"] tbody tr', {
            hasText: 'Non-final Office Action (CTNF)',
        });
        await expect(row).toContainText('office');
        await expect(row).toContainText('Office Action');
        await expect(row).toContainText('CTNF-17456789.pdf');
    });

    test('documents are uploaded, generated from templates, downloaded and deleted', async ({ page }) => {
        await openMatter(page, 'P-2021-0001');
        await page.getByRole('tab', { name: /Documents \(2\)/ }).click();
        const panel = page.locator('[data-testid="documents-panel"]');

        // Seeded: the filed specification downloads with its original name
        const downloadPromise = page.waitForEvent('download');
        await panel
            .locator('tr', { hasText: 'Specification as filed' })
            .getByRole('link', { name: 'Download' })
            .click();
        expect((await downloadPromise).suggestedFilename()).toBe('specification-as-filed.pdf');

        // Upload a new document
        await panel.getByRole('button', { name: 'Upload Document' }).click();
        const modal = page.locator('.p-dialog');
        await modal.locator('input[type="file"]').setInputFiles({
            name: 'evidence-bundle.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4 evidence bundle'),
        });
        await modal.getByPlaceholder('Defaults to the file name').fill('Evidence bundle');
        await pickOptionIn(page, modal.locator('.p-select'), 'Evidence');
        await modal.getByRole('button', { name: 'Upload' }).click();
        await expect(page.getByText('Document “Evidence bundle” filed on P-2021-0001.')).toBeVisible();
        await expect(panel.locator('tr', { hasText: 'Evidence bundle' })).toContainText('upload');

        // Generate a PDF from a communication template
        await panel.getByRole('button', { name: 'Generate from Template' }).click();
        await pickOptionIn(page, modal.locator('.p-select'), 'Filing Confirmation');
        await modal.getByPlaceholder('Defaults to the rendered subject').fill('Reporting letter PDF');
        await modal.getByRole('button', { name: 'Generate PDF' }).click();
        await expect(page.getByText('Generated “Reporting letter PDF” as a PDF on the docket.')).toBeVisible();
        const generated = panel.locator('tr', { hasText: 'Reporting letter PDF' });
        await expect(generated).toContainText('generated');

        // Clean up both new rows, restoring the seeded state
        for (const title of ['Evidence bundle', 'Reporting letter PDF']) {
            await panel
                .locator('tr', { hasText: title })
                .getByRole('button', { name: 'Delete' })
                .click();
            await page.locator('.p-confirmdialog').getByRole('button', { name: 'Delete' }).click();
            await expect(page.getByText('Document deleted.').first()).toBeVisible();
            await expect(panel.locator('tr', { hasText: title })).toHaveCount(0);
        }
    });
});
