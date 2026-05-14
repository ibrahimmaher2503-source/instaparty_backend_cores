import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['html', { outputFolder: 'tests/e2e/.report' }], ['list']],

    use: {
        baseURL: process.env.APP_URL ?? 'http://localhost:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        locale: 'en-GB',
        timezoneId: 'Africa/Cairo',
    },

    projects: [
        {
            name: 'setup',
            testMatch: '**/auth/global.setup.ts',
        },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'tests/e2e/auth/.auth-state.json',
            },
            dependencies: ['setup'],
        },
    ],

    outputDir: 'tests/e2e/.results',
});
