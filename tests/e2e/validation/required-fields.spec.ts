import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

const CREATE_PAGES = [
    { label: 'Rental service',  url: ADMIN.rentalServices  + '/create' },
    { label: 'Sale service',    url: ADMIN.saleServices    + '/create' },
    { label: 'Digital service', url: ADMIN.digitalServices + '/create' },
] as const;

for (const { label, url } of CREATE_PAGES) {
    test.describe(`${label} — required-field validation`, () => {
        test.beforeEach(async ({ page }) => {
            await page.goto(url);
        });

        test('empty submit surfaces at least one required error', async ({ page }) => {
            await page.getByRole('button', { name: /create|save/i }).first().click();
            await expect(page.getByText(/required/i).first()).toBeVisible();
        });

        test('error messages are rendered near their fields', async ({ page }) => {
            await page.getByRole('button', { name: /create|save/i }).first().click();
            // Filament wraps each field; errors appear as <p> elements inside the wrapper
            const fieldError = page
                .locator('[class*="fi-fo-field-wrp"] p')
                .or(page.locator('[class*="error"]'))
                .or(page.locator('[class*="danger"]'))
                .first();
            await expect(fieldError).toBeVisible();
        });

        test('filling English name field and re-submitting keeps page stable', async ({ page }) => {
            await page.getByRole('button', { name: /create|save/i }).first().click();
            await expect(page.getByText(/required/i).first()).toBeVisible();

            // Fill the first visible text input (name field on EN tab)
            await page.getByRole('tab').filter({ hasText: /en|english/i }).click();
            await page.locator('input[type="text"]').first().fill('E2E Placeholder Name');
            await page.getByRole('button', { name: /create|save/i }).first().click();

            // Page must remain on the form (other required fields still missing) without crashing
            await expect(page).not.toHaveURL(/error|exception/);
            await expect(page.locator('#form')).toBeVisible();
        });
    });
}
