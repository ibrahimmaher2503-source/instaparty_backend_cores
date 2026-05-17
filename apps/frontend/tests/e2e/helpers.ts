import { type Page, expect } from '@playwright/test';

export type Locale = 'en' | 'ar';

export const locales: Locale[] = ['en', 'ar'];

export async function expectRtlIfArabic(page: Page, locale: Locale) {
  const dir = await page.locator('html').getAttribute('dir');
  expect(dir).toBe(locale === 'ar' ? 'rtl' : 'ltr');
}

export async function gotoHome(page: Page, locale: Locale) {
  await page.goto(`/${locale}`);
  await expectRtlIfArabic(page, locale);
}

export const testUser = {
  phone: process.env.E2E_USER_PHONE ?? '+201111111111',
  password: process.env.E2E_USER_PASSWORD ?? 'password123',
};
