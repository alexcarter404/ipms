import { expect, test } from '@playwright/test';

test.describe('Dashboard', () => {
    test('shows portfolio stats and panels', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page.getByText('Active Matters')).toBeVisible();
        await expect(page.getByText('Open Tasks')).toBeVisible();
        await expect(page.getByText('Renewals due 90d')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Upcoming Actions' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Upcoming Renewals' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Active Portfolio' })).toBeVisible();

        // Seeded data surfaces on the dashboard
        await expect(page.getByText('P-2021-0001').first()).toBeVisible();
    });

    test('navigation reaches every module', async ({ page }) => {
        await page.goto('/dashboard');

        for (const [link, heading] of [
            ['Matters', 'Matters'],
            ['Clients', 'Clients'],
            ['Tasks', 'Tasks & Actions'],
            ['Renewals', 'Renewals'],
            ['Workflows', 'Workflows'],
            ['Templates', 'Communication Templates'],
        ]) {
            await page.getByRole('navigation').getByRole('link', { name: link, exact: true }).click();
            await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
        }
    });
});
