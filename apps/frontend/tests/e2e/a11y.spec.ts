import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const pages = ['/en', '/en/search', '/en/cart', '/en/auth/login', '/ar', '/ar/auth/login'];

for (const path of pages) {
  test(`axe a11y: ${path}`, async ({ page }) => {
    await page.goto(path);
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .disableRules(['color-contrast'])
      .analyze();

    const critical = results.violations.filter((v) => v.impact === 'critical' || v.impact === 'serious');
    expect.soft(critical, JSON.stringify(critical, null, 2)).toEqual([]);
  });
}
