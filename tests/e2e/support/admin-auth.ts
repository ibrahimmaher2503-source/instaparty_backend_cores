import { Page, expect } from '@playwright/test';
import { ADMIN } from './urls';

export async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto(ADMIN.login);
    await page.getByLabel('Email address').fill(process.env.E2E_ADMIN_EMAIL ?? 'admin@instaparty.test');
    await page.getByLabel('Password').fill(process.env.E2E_ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/\/admin(\/|$)/);
}
