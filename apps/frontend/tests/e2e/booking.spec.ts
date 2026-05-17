import { test, expect } from '@playwright/test';

test.describe('booking wizard (requires authenticated user + seed data)', () => {
  test.skip(!process.env.E2E_RUN_BOOKING, 'Set E2E_RUN_BOOKING=1 with seeded backend.');

  test('cart shows empty state when no draft', async ({ page }) => {
    await page.context().addCookies([
      {
        name: 'instaparty_token',
        value: process.env.E2E_TOKEN ?? 'fake',
        domain: 'localhost',
        path: '/',
      },
    ]);
    await page.goto('/en/cart');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('checkout/event redirects unauthenticated user', async ({ page }) => {
    await page.goto('/en/checkout/event');
    await expect(page).toHaveURL(/\/auth\/login\?next=/);
  });
});
