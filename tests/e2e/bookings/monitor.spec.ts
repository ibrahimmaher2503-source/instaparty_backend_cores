import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Bookings / Negotiation monitor', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(ADMIN.bookingsMonitor);
    });

    test('heading is "Negotiation Monitoring"', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Negotiation Monitoring' })).toBeVisible();
    });

    test('table renders', async ({ page }) => {
        await expect(page.locator('table')).toBeVisible();
    });

    test('filter panel has a Product Type filter', async ({ page }) => {
        await page.getByRole('button', { name: /filters/i }).click();
        await expect(page.getByText(/product type/i)).toBeVisible();
    });

    test('search input is present', async ({ page }) => {
        await expect(page.getByPlaceholder(/search/i)).toBeVisible();
    });
});
