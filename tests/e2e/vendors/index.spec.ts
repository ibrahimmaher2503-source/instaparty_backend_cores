import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Vendor profiles index', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(ADMIN.vendors);
    });

    test('heading is "Vendor Profiles"', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Vendor Profiles' })).toBeVisible();
    });

    test('table renders with a header row', async ({ page }) => {
        await expect(page.locator('table thead')).toBeVisible();
    });

    test('search input is present', async ({ page }) => {
        await expect(page.getByPlaceholder(/search/i)).toBeVisible();
    });

    test('"Filters" button is present', async ({ page }) => {
        await expect(page.getByRole('button', { name: /filters/i })).toBeVisible();
    });

    test('Approval Status column header is visible', async ({ page }) => {
        await expect(
            page.getByRole('columnheader', { name: /approval|status/i })
        ).toBeVisible();
    });

    test('garbage search shows no-results state', async ({ page }) => {
        await page.getByPlaceholder(/search/i).fill('XPWTEST99999NONEXISTENT');
        await page.waitForTimeout(600);
        await expect(page.getByText(/no results/i)).toBeVisible();
    });
});
