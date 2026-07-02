import { expect, test } from '@playwright/test';

test.describe('Global search', () => {
    test('typeahead finds records across the system, grouped by type', async ({ page }) => {
        await page.goto('/dashboard');

        const search = page.getByRole('searchbox', { name: 'Global search' });
        await search.click();
        await search.fill('acme');

        const dropdown = page.getByTestId('global-search-results');
        await expect(dropdown.getByText('Clients', { exact: true })).toBeVisible();
        await expect(dropdown.getByText('Acme Industries Ltd').first()).toBeVisible();
        await expect(dropdown.getByText('Entities', { exact: true })).toBeVisible();
        await expect(dropdown.getByText('Acme Industries Inc')).toBeVisible();
        await expect(dropdown.getByText('Contacts', { exact: true })).toBeVisible();
        await expect(dropdown.getByText('Acme IP Docketing')).toBeVisible();
    });

    test('results filter as the user keeps typing', async ({ page }) => {
        await page.goto('/dashboard');

        const search = page.getByRole('searchbox', { name: 'Global search' });
        await search.fill('P-2021');

        const dropdown = page.getByTestId('global-search-results');
        await expect(dropdown.getByText(/P-2021-0001/)).toBeVisible();
        await expect(dropdown.getByText(/P-2021-0003/)).toBeVisible();

        await search.fill('P-2021-0003');
        await expect(dropdown.getByText(/P-2021-0003/)).toBeVisible();
        await expect(dropdown.getByText(/P-2021-0001/)).toBeHidden();
    });

    test('clicking a result navigates to it', async ({ page }) => {
        await page.goto('/tasks');

        const search = page.getByRole('searchbox', { name: 'Global search' });
        await search.fill('NOVASHIELD');

        await page.getByTestId('global-search-results').getByText(/TM-2023-0001/).click();

        await expect(page).toHaveURL(/matters\/\d+/);
        await expect(page.getByRole('heading', { name: 'TM-2023-0001' })).toBeVisible();
    });

    test('keyboard: arrows + enter select a result, escape closes', async ({ page }) => {
        await page.goto('/dashboard');

        const search = page.getByRole('searchbox', { name: 'Global search' });
        await search.fill('Self-sealing');

        const dropdown = page.getByTestId('global-search-results');
        await expect(dropdown.getByText(/P-2021-0001/)).toBeVisible();

        await search.press('ArrowDown');
        await search.press('Enter');
        await expect(page).toHaveURL(/matters\/\d+/);

        // Escape closes the dropdown
        const search2 = page.getByRole('searchbox', { name: 'Global search' });
        await search2.fill('Self-sealing');
        await expect(page.getByTestId('global-search-results')).toBeVisible();
        await search2.press('Escape');
        await expect(page.getByTestId('global-search-results')).toBeHidden();
    });

    test('no-result queries say so', async ({ page }) => {
        await page.goto('/dashboard');

        const search = page.getByRole('searchbox', { name: 'Global search' });
        await search.fill('zzzznothing');

        await expect(page.getByText('No results for “zzzznothing”.')).toBeVisible();
    });
});
