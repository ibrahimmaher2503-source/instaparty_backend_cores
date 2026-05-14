import { test, expect } from '@playwright/test';

test.describe('Failure states', () => {
    test('visiting a non-existent admin route does not show a PHP stack trace', async ({ page }) => {
        await page.goto('/admin/this-page-absolutely-does-not-exist-xyz');
        const body = await page.content();
        // Production-mode error pages must not leak internals
        expect(body).not.toContain('Symfony\\Component');
        expect(body).not.toContain('vendor/laravel');
        expect(body).not.toContain('stack trace');
    });

    test('viewing a non-existent booking record returns a handled response', async ({ page }) => {
        const response = await page.goto('/admin/bookings/999999999');
        // Filament handles missing records with a 404 or a redirect — never a 500
        expect(response?.status()).not.toBe(500);
        const body = await page.content();
        expect(body).not.toContain('stack trace');
    });

    test('viewing a non-existent booking edit page returns a handled response', async ({ page }) => {
        const response = await page.goto('/admin/bookings/999999999/edit');
        expect(response?.status()).not.toBe(500);
    });

    test('clearing cookies mid-session redirects to login', async ({ page }) => {
        await page.goto('/admin');
        await expect(page).not.toHaveURL(/\/login/);

        // Simulate session expiry
        await page.context().clearCookies();
        await page.goto('/admin/bookings');
        await expect(page).toHaveURL(/\/admin\/login/);
    });
});
