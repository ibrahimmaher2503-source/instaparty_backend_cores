import { test, expect } from '@playwright/test';
import { locales } from './helpers';

for (const locale of locales) {
  test.describe(`auth flow (${locale})`, () => {
    test('/me/* without a token redirects to login with next param', async ({ page }) => {
      await page.goto(`/${locale}/me/profile`);
      await expect(page).toHaveURL(/\/auth\/login\?next=/);
    });

    test('login page renders both tabs', async ({ page }) => {
      await page.goto(`/${locale}/auth/login`);
      await expect(page.getByRole('tab').first()).toBeVisible();
      await expect(page.locator('input[type=password]').first()).toBeVisible();
    });

    test('register form validates phone format', async ({ page }) => {
      await page.goto(`/${locale}/auth/register`);
      await page.locator('input[type=password]').first().fill('short');
      await page.locator('button[type=submit]').click();
      await expect(page.locator('input[name="phone_e164"]')).toBeVisible();
    });
  });
}
