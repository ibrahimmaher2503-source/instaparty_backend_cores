import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Rental services — index', () => {
    test('heading is "Rental Services"', async ({ page }) => {
        await page.goto(ADMIN.rentalServices);
        await expect(page.getByRole('heading', { name: 'Rental Services' })).toBeVisible();
    });

    test('table renders', async ({ page }) => {
        await page.goto(ADMIN.rentalServices);
        await expect(page.locator('table')).toBeVisible();
    });
});

test.describe('Rental services — create form', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(ADMIN.rentalServices + '/create');
    });

    test('create page renders a heading', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /create|new rental/i })).toBeVisible();
    });

    test('form has English and Arabic translation tabs', async ({ page }) => {
        await expect(page.getByRole('tab').filter({ hasText: /en|english/i })).toBeVisible();
        await expect(page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i })).toBeVisible();
    });

    test('Arabic tab becomes active on click', async ({ page }) => {
        const arTab = page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i });
        await arTab.click();
        await expect(arTab).toHaveAttribute('aria-selected', 'true');
    });

    test('empty submit surfaces required field errors', async ({ page }) => {
        await page.getByRole('button', { name: /create|save/i }).first().click();
        await expect(page.getByText(/required/i).first()).toBeVisible();
    });
});
