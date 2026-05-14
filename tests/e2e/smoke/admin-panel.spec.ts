import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Admin panel smoke tests', () => {
    test('dashboard loads', async ({ page }) => {
        await page.goto(ADMIN.dashboard);
        await expect(page).toHaveURL(/\/admin/);
        await expect(page.locator('nav')).toBeVisible();
    });

    test('bookings index loads', async ({ page }) => {
        await page.goto(ADMIN.bookings);
        await expect(page.getByRole('heading', { name: /booking/i })).toBeVisible();
    });

    test('bookings monitor loads', async ({ page }) => {
        await page.goto(ADMIN.bookingsMonitor);
        await expect(page.getByRole('heading', { name: /monitor/i })).toBeVisible();
    });

    test('rental services index loads', async ({ page }) => {
        await page.goto(ADMIN.rentalServices);
        await expect(page.getByRole('heading', { name: /rental/i })).toBeVisible();
    });

    test('sale services index loads', async ({ page }) => {
        await page.goto(ADMIN.saleServices);
        await expect(page.getByRole('heading', { name: /sale/i })).toBeVisible();
    });

    test('digital services index loads', async ({ page }) => {
        await page.goto(ADMIN.digitalServices);
        await expect(page.getByRole('heading', { name: /digital/i })).toBeVisible();
    });

    test('vendor profiles index loads', async ({ page }) => {
        await page.goto(ADMIN.vendors);
        await expect(page.getByRole('heading', { name: /vendor/i })).toBeVisible();
    });
});
