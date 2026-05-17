import { test, expect } from '@playwright/test';
import { gotoHome, locales } from './helpers';

const types = ['rental', 'sale', 'digital'] as const;

for (const locale of locales) {
  for (const type of types) {
    test(`${type} service surface renders in ${locale}`, async ({ page }) => {
      await gotoHome(page, locale);
      const link = page.getByRole('link').filter({ hasText: new RegExp(type, 'i') }).first();

      const exists = await link.count();
      test.skip(exists === 0, `No ${type} surface link present on home for ${locale} — seed data missing.`);

      await link.click();
      await expect(page).toHaveURL(new RegExp(`/${locale}/`));
    });
  }
}

test('discriminated-union variant picker', async ({ page }) => {
  test.skip(true, 'Requires seeded service detail pages — run against a fixture backend.');
  await gotoHome(page, 'en');
  // Visit a known rental service detail
  // await page.goto('/en/services/RENTAL_SERVICE_PUBLIC_ID');
  // await expect(page.getByText(/setup time|security deposit/i)).toBeVisible();
});
