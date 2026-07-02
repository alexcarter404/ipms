import { expect, test } from '@playwright/test';
import { field, msToNextTotpWindow, totp } from './helpers.mjs';

// This journey starts unauthenticated — don't reuse the shared admin session.
test.use({ storageState: { cookies: [], origins: [] } });

const confirmPasswordIfAsked = async (page) => {
    const dialog = page.getByRole('dialog').filter({ hasText: 'Confirm Password' });
    await dialog.waitFor({ state: 'visible', timeout: 3000 }).catch(() => null);
    if (await dialog.isVisible()) {
        await dialog.locator('input[type="password"]').fill('password');
        await dialog.getByRole('button', { name: 'Confirm' }).click();
        await dialog.waitFor({ state: 'hidden' });
    }
};

test('full two-factor lifecycle: enrol, challenge login, disable', async ({ page }) => {
    test.setTimeout(120_000);

    // --- Sign in normally (no 2FA yet) ---
    await page.goto('/login');
    await page.locator('#email').fill('jordan@example.com');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    // --- Enrol from the profile page ---
    await page.goto('/profile');
    await expect(page.getByRole('heading', { name: 'Two-Factor Authentication' })).toBeVisible();

    await page.getByRole('button', { name: 'Enable', exact: true }).click();
    await confirmPasswordIfAsked(page);

    // QR code + setup key appear; read the secret like an authenticator app
    await expect(page.getByText('Setup key:')).toBeVisible();
    const secret = (await page.getByTestId('two-factor-secret').innerText()).trim();
    expect(secret.length).toBeGreaterThanOrEqual(16);

    const enrolCode = totp(secret);
    await field(page, 'Code').fill(enrolCode);
    await page.getByRole('button', { name: 'Confirm', exact: true }).click();

    // Recovery codes are revealed once enabled
    await expect(page.getByText('Two-factor authentication is enabled.')).toBeVisible();
    await expect(page.getByTestId('recovery-codes')).toBeVisible();

    // --- Log out; logging back in now demands the challenge ---
    await page.getByRole('button', { name: 'Jordan Reeves' }).click();
    await page.getByRole('button', { name: 'Log Out' }).click();
    await expect(page).toHaveURL(/login|\/$/);

    await page.goto('/login');
    await page.locator('#email').fill('jordan@example.com');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/two-factor-challenge/);
    await expect(page.getByText('authenticator application')).toBeVisible();

    // Fortify blocks TOTP replays: if we're still in the window that the
    // enrolment code was consumed in, wait for the next one.
    let challengeCode = totp(secret);
    if (challengeCode === enrolCode) {
        await page.waitForTimeout(msToNextTotpWindow());
        challengeCode = totp(secret);
    }

    await page.locator('#code').fill(challengeCode);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    // --- Clean up: disable so the seeded account is 2FA-free again ---
    await page.goto('/profile');
    await page.getByRole('button', { name: 'Disable', exact: true }).click();
    await confirmPasswordIfAsked(page);
    await expect(page.getByText('You have not enabled two-factor authentication.')).toBeVisible();
});
