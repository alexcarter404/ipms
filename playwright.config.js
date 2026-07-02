import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const E2E_DB = path.resolve(import.meta.dirname ?? '.', 'database/e2e.sqlite');

/**
 * E2E suite. Boots `php artisan serve` against a dedicated SQLite database
 * (database/e2e.sqlite) that is migrated + seeded in global-setup.
 * Run with: npm run test:e2e
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    // Tests mutate a shared seeded database, so run them serially.
    workers: 1,
    fullyParallel: false,
    reporter: [['list']],
    use: {
        baseURL: 'http://127.0.0.1:8123',
        trace: 'retain-on-failure',
        // Use a system-provided Chromium when available (e.g. sandboxed CI
        // images that pre-install browsers outside Playwright's registry).
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH
            ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH }
            : {},
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.mjs/ },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'tests/e2e/.auth/user.json',
            },
            dependencies: ['setup'],
        },
    ],
    webServer: {
        // Reset + seed the E2E database, then serve. The health-check URL
        // only returns 200 once migration has finished.
        command: 'touch database/e2e.sqlite && php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8123',
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: !process.env.CI,
        env: {
            APP_ENV: 'testing',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: E2E_DB,
        },
    },
});
