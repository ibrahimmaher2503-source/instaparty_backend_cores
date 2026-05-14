import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.use({ viewport: { width: 375, height: 812 } });

test.describe('Mobile 375 px viewport', () => {
    test('dashboard loads without a JS error page', async ({ page }) => {
        await page.goto(ADMIN.dashboard);
        await expect(page.locator('body')).toBeVisible();
        await expect(page).not.toHaveURL(/error|exception/);
    });

    test('bookings index renders table at 375 px', async ({ page }) => {
        await page.goto(ADMIN.bookings);
        await expect(page.getByRole('heading', { name: 'Bookings' })).toBeVisible();
        await expect(page.locator('table')).toBeVisible();
    });

    test('vendor profiles index renders at 375 px', async ({ page }) => {
        await page.goto(ADMIN.vendors);
        await expect(page.getByRole('heading', { name: 'Vendor Profiles' })).toBeVisible();
    });

    test('rental services create form is usable at 375 px', async ({ page }) => {
        await page.goto(ADMIN.rentalServices + '/create');
        await expect(page.locator('form')).toBeVisible();
        // Translation tabs must still be tappable
        await expect(page.getByRole('tab').filter({ hasText: /en|english/i })).toBeVisible();
    });

    test('sidebar nav or toggle is accessible on mobile', async ({ page }) => {
        await page.goto(ADMIN.dashboard);
        // Filament collapses the sidebar on small screens and shows a toggle button
        const nav = page.locator('nav');
        const toggle = page.locator('[aria-label*="sidebar"], [data-toggle-sidebar], .fi-sidebar-close-btn').first();
        await expect(nav.or(toggle)).toBeVisible();
    });
});
