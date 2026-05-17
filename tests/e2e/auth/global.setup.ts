import { test as setup } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

const PROJECT_ROOT = process.cwd();
const AUTH_STATE   = path.join(PROJECT_ROOT, 'tests/e2e/auth/.auth-state.json');

const ADMIN_EMAIL    = process.env.E2E_ADMIN_EMAIL    ?? 'admin@instaparty.test';
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? 'password';

setup('seed admin user and authenticate', async ({ page }) => {
    try {
        execSync(
            `php artisan e2e:seed-admin --email="${ADMIN_EMAIL}" --password="${ADMIN_PASSWORD}"`,
            { cwd: PROJECT_ROOT, stdio: 'pipe' },
        );
    } catch (err: any) {
        throw new Error(
            `e2e:seed-admin failed.\nstdout: ${err.stdout?.toString()}\nstderr: ${err.stderr?.toString()}`,
        );
    }

    await page.goto('/admin/login');
    await page.getByLabel('Email address').fill(ADMIN_EMAIL);
    await page.getByLabel('Password').fill(ADMIN_PASSWORD);

    // Wait for the Livewire action + redirect in one shot
    await Promise.all([
        page.waitForURL(url => !url.toString().includes('/login'), { timeout: 20_000 }),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);

    await page.waitForLoadState('domcontentloaded');
    console.log('Authenticated URL:', page.url());

    await page.context().storageState({ path: AUTH_STATE });
});
