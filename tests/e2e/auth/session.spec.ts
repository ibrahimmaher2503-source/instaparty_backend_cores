import { test, expect } from '@playwright/test';

test.describe('Authenticated session', () => {
    test('stored auth reaches /admin without a redirect', async ({ page }) => {
        await page.goto('/admin');
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.locator('nav')).toBeVisible();
    });
});

test.describe('Unauthenticated access', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('/admin redirects to /admin/login', async ({ page }) => {
        await page.goto('/admin');
        await expect(page).toHaveURL(/\/admin\/login/);
    });

    test('/admin/bookings redirects to /admin/login', async ({ page }) => {
        await page.goto('/admin/bookings');
        await expect(page).toHaveURL(/\/admin\/login/);
    });

    test('/admin/vendor-profiles redirects to /admin/login', async ({ page }) => {
        await page.goto('/admin/vendor-profiles');
        await expect(page).toHaveURL(/\/admin\/login/);
    });
});
