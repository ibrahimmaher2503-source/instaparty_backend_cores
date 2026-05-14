import { test, expect } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Login', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin/login');
    });

    test('renders email, password and sign-in button', async ({ page }) => {
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(page.getByLabel('Password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    });

    test('rejects wrong credentials', async ({ page }) => {
        await page.getByLabel('Email address').fill('nobody@example.com');
        await page.getByLabel('Password').fill('wrong');
        await page.getByRole('button', { name: 'Sign in' }).click();
        await expect(page.getByText(/these credentials do not match/i)).toBeVisible();
    });

    test('successful login reaches the dashboard', async ({ page }) => {
        const email    = process.env.E2E_ADMIN_EMAIL    ?? 'admin@instaparty.test';
        const password = process.env.E2E_ADMIN_PASSWORD ?? 'password';

        await page.getByLabel('Email address').fill(email);
        await page.getByLabel('Password').fill(password);
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page).toHaveURL(/\/admin(\/|$)/);
        await expect(page).not.toHaveURL(/\/login/);
    });
});
