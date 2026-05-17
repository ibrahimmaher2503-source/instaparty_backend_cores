import { test, expect } from '@playwright/test';

test('theme tokens are injected into :root', async ({ page }) => {
  await page.goto('/en');
  const styleTags = await page.locator('head style').count();
  expect(styleTags).toBeGreaterThan(0);

  const primary = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--color-primary-500').trim(),
  );
  expect(primary.length).toBeGreaterThan(0);
});

test('Arabic loads the Arabic font family', async ({ page }) => {
  await page.goto('/ar');
  const dir = await page.locator('html').getAttribute('dir');
  expect(dir).toBe('rtl');
  const arabicVar = await page.evaluate(() =>
    getComputedStyle(document.documentElement).getPropertyValue('--font-arabic').trim(),
  );
  expect(arabicVar.length).toBeGreaterThan(0);
});
