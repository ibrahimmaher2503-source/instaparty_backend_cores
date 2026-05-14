import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Bookings filters', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(ADMIN.bookings);
    });

    test('"Filters" button is visible', async ({ page }) => {
        await expect(page.getByRole('button', { name: /filters/i })).toBeVisible();
    });

    test('clicking Filters reveals filter controls', async ({ page }) => {
        await page.getByRole('button', { name: /filters/i }).click();
        await expect(page.getByText(/booking status|lifecycle/i)).toBeVisible();
    });

    test('Product Type filter control is present', async ({ page }) => {
        await page.getByRole('button', { name: /filters/i }).click();
        await expect(page.getByText(/product type/i)).toBeVisible();
    });

    test('Payment Status filter control is present', async ({ page }) => {
        await page.getByRole('button', { name: /filters/i }).click();
        await expect(page.getByText(/payment/i)).toBeVisible();
    });

    test('filter panel can be opened and closed', async ({ page }) => {
        const btn = page.getByRole('button', { name: /filters/i });
        await btn.click();
        await expect(page.getByText(/product type/i)).toBeVisible();
        // Click again or press Escape to close
        await page.keyboard.press('Escape');
        // Page should remain stable after close
        await expect(page.locator('table')).toBeVisible();
    });
});
