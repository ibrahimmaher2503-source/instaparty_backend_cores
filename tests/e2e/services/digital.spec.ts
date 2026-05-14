import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Digital services — index', () => {
    test('heading is "Digital Services"', async ({ page }) => {
        await page.goto(ADMIN.digitalServices);
        await expect(page.getByRole('heading', { name: 'Digital Services' })).toBeVisible();
    });

    test('table renders', async ({ page }) => {
        await page.goto(ADMIN.digitalServices);
        await expect(page.locator('table')).toBeVisible();
    });
});

test.describe('Digital services — create form', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(ADMIN.digitalServices + '/create');
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

    test('active tab panel is visible after tab switch', async ({ page }) => {
        const arTab = page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i });
        await arTab.click();
        await expect(page.getByRole('tabpanel')).toBeVisible();
    });

    test('empty submit surfaces required field errors', async ({ page }) => {
        await page.getByRole('button', { name: /create|save/i }).first().click();
        await expect(page.getByText(/required/i).first()).toBeVisible();
    });
});
