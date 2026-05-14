import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Sale services — index', () => {
    test('heading is "Sale Services"', async ({ page }) => {
        await page.goto(ADMIN.saleServices);
        await expect(page.getByRole('heading', { name: 'Sale Services' })).toBeVisible();
    });

    test('table renders', async ({ page }) => {
        await page.goto(ADMIN.saleServices);
        await expect(page.locator('table')).toBeVisible();
    });
});

test.describe('Sale services — create form', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(ADMIN.saleServices + '/create');
    });

    test('form has English and Arabic translation tabs', async ({ page }) => {
        await expect(page.getByRole('tab').filter({ hasText: /en|english/i })).toBeVisible();
        await expect(page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i })).toBeVisible();
    });

    test('switching to Arabic tab updates aria-selected', async ({ page }) => {
        const arTab = page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i });
        await arTab.click();
        await expect(arTab).toHaveAttribute('aria-selected', 'true');
    });

    test('switching back to English tab updates aria-selected', async ({ page }) => {
        const arTab = page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i });
        const enTab = page.getByRole('tab').filter({ hasText: /en|english/i });
        await arTab.click();
        await enTab.click();
        await expect(enTab).toHaveAttribute('aria-selected', 'true');
    });

    test('empty submit surfaces required field errors', async ({ page }) => {
        await page.getByRole('button', { name: /create|save/i }).first().click();
        await expect(page.getByText(/required/i).first()).toBeVisible();
    });
});
