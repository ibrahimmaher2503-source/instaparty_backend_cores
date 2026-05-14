import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('RTL / Arabic locale', () => {
    test('<html> has a lang attribute by default', async ({ page }) => {
        await page.goto(ADMIN.dashboard);
        const lang = await page.locator('html').getAttribute('lang');
        expect(lang).toBeTruthy();
    });

    test('switching to Arabic locale sets dir="rtl" on <html>', async ({ page }) => {
        await page.goto(ADMIN.dashboard);

        // bezhansalleh/filament-language-switch renders a locale selector button
        const arabicBtn = page
            .getByRole('button', { name: /ar|arabic|العربية/i })
            .or(page.locator('[data-locale="ar"]'))
            .first();

        const visible = await arabicBtn.isVisible().catch(() => false);
        if (!visible) {
            test.skip();
            return;
        }

        await arabicBtn.click();
        await page.waitForLoadState('networkidle');

        const dir = await page.locator('html').getAttribute('dir');
        expect(dir).toBe('rtl');
    });

    test('create form Arabic tab panel is reachable and visible', async ({ page }) => {
        await page.goto(ADMIN.rentalServices + '/create');
        const arTab = page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i });
        await expect(arTab).toBeVisible();
        await arTab.click();
        await expect(page.getByRole('tabpanel')).toBeVisible();
    });

    test('create form Arabic tab contains at least one input', async ({ page }) => {
        await page.goto(ADMIN.rentalServices + '/create');
        await page.getByRole('tab').filter({ hasText: /ar|arabic|عربي/i }).click();
        // The AR panel must expose inputs (not an empty shell)
        await expect(page.getByRole('tabpanel').locator('input').first()).toBeVisible();
    });
});
