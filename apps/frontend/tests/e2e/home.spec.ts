import { test, expect } from '@playwright/test';
import { gotoHome, locales } from './helpers';

for (const locale of locales) {
  test.describe(`home page (${locale})`, () => {
    test('renders branding and a header', async ({ page }) => {
      await gotoHome(page, locale);
      await expect(page.locator('header')).toBeVisible();
      await expect(page.locator('main')).toBeVisible();
    });

    test('language switcher flips locale', async ({ page }) => {
      await gotoHome(page, locale);
      const other = locale === 'ar' ? 'en' : 'ar';
      await page.getByRole('link', { name: other === 'ar' ? /العربية/ : /English/i }).first().click();
      await expect(page).toHaveURL(new RegExp(`/${other}(?:/|$)`));
      const dir = await page.locator('html').getAttribute('dir');
      expect(dir).toBe(other === 'ar' ? 'rtl' : 'ltr');
    });
  });
}
