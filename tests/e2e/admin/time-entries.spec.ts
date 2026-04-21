import { test, expect } from '../fixtures/base';
import { getNonce, getApiUrl } from '../helpers/api';

/**
 * Time Entries CRUD tests.
 *
 * The TimeEntryForm requires both an employee and a ticket to exist.
 * These tests rely on the demo-seeded environment having at least one of each.
 * If the dropdowns are empty, the tests will surface that gap naturally.
 *
 * The entry description contains 'E2E Test' for easy identification and cleanup.
 */
test.describe.serial('Admin > Time Entries CRUD', () => {
  const description = `E2E Test Entry - ${Date.now()}`;

  // Fixed times in the past (safe for any time entry validation)
  const startTime = '2026-01-15T09:00';
  const endTime   = '2026-01-15T10:30';

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-time');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });
  });

  test('can create a time entry and see it in the list', async ({ page, consoleErrors }) => {
    await page.getByRole('button', { name: 'Log Time Entry' }).click();
    await expect(page.getByRole('heading', { name: /Log Time Entry/i })).toBeVisible();

    // Wait for employee and ticket dropdowns to populate
    await page.waitForFunction(() => {
      const employee = document.getElementById('pet-time-employee') as HTMLSelectElement | null;
      const ticket = document.getElementById('pet-time-ticket') as HTMLSelectElement | null;
      return Boolean(employee && ticket && employee.options.length > 1 && ticket.options.length > 1);
    }, { timeout: 15_000 });

    // Select the first available employee
    const employeeSelect = page.locator('#pet-time-employee');
    await employeeSelect.selectOption({ index: 1 });

    // Select the first available ticket
    const ticketSelect = page.locator('#pet-time-ticket');
    await ticketSelect.selectOption({ index: 1 });

    // Fill times
    await page.getByLabel('Start Time:').fill(startTime);
    await page.getByLabel('End Time:').fill(endTime);

    // Fill description for identification
    await page.getByLabel('Description:').fill(description);
    const saveButton = page.getByRole('button', { name: 'Save Entry' });
    await expect(saveButton).toBeEnabled({ timeout: 10_000 });

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/time-entries') && resp.request().method() === 'POST'
    );
    await page.evaluate(() => {
      const form = document.querySelector('.pet-form-container form') as HTMLFormElement | null;
      form?.requestSubmit();
    });
    const response = await responsePromise;
    expect(response.status()).toBe(201);

    // Form closes; description should appear in the table
    await expect(page.locator('td', { hasText: description })).toBeVisible({ timeout: 10_000 });
  });

  test('can archive a time entry via kebab menu', async ({ page, consoleErrors }) => {

    const row = page.locator('tr', { hasText: description });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Archive', { exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Archive' }).click();

    const archiveResponse = await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/time-entries/') && resp.request().method() === 'DELETE'
    );

    // Verify the DELETE response was successful
    expect(archiveResponse.ok()).toBeTruthy();
  });

  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-time');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      if (!nonce || !apiUrl) return;

      const res = await page.request.get(`${apiUrl}/time-entries`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (!res.ok()) return;

      const entries = await res.json();
      for (const entry of entries) {
        if (typeof entry.description === 'string' && entry.description.startsWith('E2E Test')) {
          await page.request.delete(`${apiUrl}/time-entries/${entry.id}`, {
            headers: { 'X-WP-Nonce': nonce },
          });
        }
      }
    } catch {
      // best-effort cleanup
    } finally {
      await context.close();
    }
  });
});
