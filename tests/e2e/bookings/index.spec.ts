import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Bookings index', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(ADMIN.bookings);
    });

    test('heading is "Bookings"', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Bookings' })).toBeVisible();
    });

    test('table renders with a header row', async ({ page }) => {
        await expect(page.locator('table thead')).toBeVisible();
    });

    test('search input is present', async ({ page }) => {
        await expect(page.getByPlaceholder(/search/i)).toBeVisible();
    });

    test('Reference Number column header is visible', async ({ page }) => {
        await expect(page.getByRole('columnheader', { name: /reference/i })).toBeVisible();
    });

    test('Booking Status column header is visible', async ({ page }) => {
        await expect(page.getByRole('columnheader', { name: /booking status|status/i })).toBeVisible();
    });

    test('garbage search term shows no-results state', async ({ page }) => {
        await page.getByPlaceholder(/search/i).fill('XPWTEST99999NONEXISTENT');
        await page.waitForTimeout(600); // Livewire debounce
        await expect(page.getByText(/no results/i)).toBeVisible();
    });
});
