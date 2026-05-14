import { test as setup, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

const AUTH_STATE = path.join(__dirname, '.auth-state.json');

const ADMIN_EMAIL    = process.env.E2E_ADMIN_EMAIL    ?? 'admin@instaparty.test';
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? 'password';

setup('seed admin user and authenticate', async ({ page }) => {
    // Ensure the admin user exists via Artisan
    execSync(
        `php artisan e2e:seed-admin --email="${ADMIN_EMAIL}" --password="${ADMIN_PASSWORD}"`,
        { cwd: path.resolve(__dirname, '../../../..'), stdio: 'inherit' },
    );

    await page.goto('/admin/login');
    await page.getByLabel('Email address').fill(ADMIN_EMAIL);
    await page.getByLabel('Password').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/admin(\/|$)/);

    await page.context().storageState({ path: AUTH_STATE });
});
