import { test, expect } from '@playwright/test';
import { ADMIN } from '../support/urls';

test.describe('Filament confirmation dialogs', () => {
    test('bulk-delete action triggers an alertdialog', async ({ page }) => {
        await page.goto(ADMIN.vendors);

        const checkboxes = page.locator('table tbody input[type="checkbox"]');
        const rowCount = await checkboxes.count();

        if (rowCount === 0) {
            // No vendor records seeded — skip rather than fail
            test.skip();
            return;
        }

        await checkboxes.first().check();

        // Filament shows a bulk-actions bar once a row is selected
        const deleteBtn = page
            .getByRole('button', { name: /delete selected/i })
            .or(page.getByRole('button', { name: /delete/i }).last());
        await expect(deleteBtn).toBeVisible();
        await deleteBtn.click();

        // Filament renders delete confirmation as alertdialog or dialog
        const dialog = page.getByRole('alertdialog').or(page.getByRole('dialog'));
        await expect(dialog).toBeVisible();

        // Cancel to avoid mutating test data
        await page.getByRole('button', { name: /cancel/i }).click();
        await expect(dialog).not.toBeVisible();
    });

    test('row-level delete action triggers a confirmation dialog', async ({ page }) => {
        await page.goto(ADMIN.rentalServices);

        const rows = page.locator('table tbody tr');
        const rowCount = await rows.count();

        if (rowCount === 0) {
            test.skip();
            return;
        }

        // Hover first row to reveal inline actions
        await rows.first().hover();
        const deleteAction = rows.first().getByRole('button', { name: /delete/i });
        await expect(deleteAction).toBeVisible();
        await deleteAction.click();

        const dialog = page.getByRole('alertdialog').or(page.getByRole('dialog'));
        await expect(dialog).toBeVisible();
        await page.getByRole('button', { name: /cancel/i }).click();
    });
});
