import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Loading states', () => {
    test('rapid search input does not crash the page', async ({ page }) => {
        await page.goto(ADMIN.bookings);
        const search = page.getByPlaceholder(/search/i);

        // Rapid typing exercises Livewire debounce
        await search.fill('a');
        await search.fill('ab');
        await search.fill('abc');

        await page.waitForTimeout(700); // let debounce settle
        await expect(page).not.toHaveURL(/error|exception/);
        await expect(page.locator('table')).toBeVisible();
    });

    test('opening filter panel keeps table visible', async ({ page }) => {
        await page.goto(ADMIN.bookings);
        await page.getByRole('button', { name: /filters/i }).click();
        await page.waitForTimeout(300);
        await expect(page.locator('table')).toBeVisible();
    });

    test('navigating between admin pages does not show a blank screen', async ({ page }) => {
        await page.goto(ADMIN.bookings);
        await page.goto(ADMIN.rentalServices);
        await page.goto(ADMIN.vendors);
        await expect(page.getByRole('heading', { name: 'Vendor Profiles' })).toBeVisible();
    });

    test('Livewire wire: directives are present on table pages', async ({ page }) => {
        await page.goto(ADMIN.bookings);
        const html = await page.content();
        // Confirms Livewire is hydrating the page (not a plain Blade static render)
        expect(html).toContain('wire:');
    });
});
