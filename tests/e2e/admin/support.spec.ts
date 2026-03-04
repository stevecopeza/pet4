import { test, expect } from '../fixtures/base';
import { testLabel } from '../fixtures/test-data';
import { getNonce, getApiUrl } from '../helpers/api';

test.describe.serial('Admin > Support Tickets CRUD', () => {
  const ticketSubject = testLabel('Ticket');

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });
  });

  test('can create a ticket and see it in the list', async ({ page, consoleErrors }) => {
    await page.getByRole('button', { name: 'Create New Ticket' }).click();
    await expect(page.getByRole('heading', { name: /Create New Ticket/i })).toBeVisible();

    // Wait for the customer list to populate
    await page.waitForFunction(
      () => {
        const sel = document.getElementById('pet-ticket-customer') as HTMLSelectElement | null;
        return sel !== null && sel.options.length > 1;
      },
      { timeout: 10_000 }
    );

    // Select the first real customer
    const customerSelect = page.locator('#pet-ticket-customer');
    await customerSelect.selectOption({ index: 1 });

    // Verify React picked up the selection (controlled component sanity check)
    await expect(customerSelect).not.toHaveValue('');

    // Let all async effects (site-loading, etc.) settle
    await page.waitForLoadState('networkidle');

    await page.getByLabel('Subject:', { exact: true }).fill(ticketSubject);
    await page.getByLabel('Description:', { exact: true }).fill('E2E test ticket — can be deleted.');
    // Source defaults to "portal" — no change needed

    // Verify Subject is filled (scroll may have moved it off-screen)
    await expect(page.getByLabel('Subject:', { exact: true })).toHaveValue(ticketSubject);

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/tickets') && resp.request().method() === 'POST'
    );
    // Use requestSubmit() — programmatic submit with native validation
    await page.evaluate(() => {
      const form = document.querySelector('.pet-form-container form') as HTMLFormElement;
      form.requestSubmit();
    });
    const response = await responsePromise;
    expect(response.status()).toBe(201);

    // The form closes and the ticket list reloads
    await expect(page.getByRole('button', { name: ticketSubject })).toBeVisible({ timeout: 10_000 });
  });

  test('can open ticket details', async ({ page, consoleErrors }) => {
    // The subject is rendered as a button link to TicketDetails
    await page.getByRole('button', { name: ticketSubject }).click();
    // TicketDetails shows — a Back button and the subject heading should appear
    await expect(page.getByRole('button', { name: /Back/i }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('can archive a ticket via kebab menu', async ({ page, consoleErrors }) => {
    page.on('dialog', (dialog) => dialog.accept());

    const row = page.locator('tr', { hasText: ticketSubject });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Archive', { exact: true }).click();

    await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/tickets/') && resp.request().method() === 'DELETE'
    );

    await expect(page.getByRole('button', { name: ticketSubject })).toBeHidden({ timeout: 5_000 });
  });

  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-support');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      if (!nonce || !apiUrl) return;

      const res = await page.request.get(`${apiUrl}/tickets`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (!res.ok()) return;

      const tickets = await res.json();
      for (const ticket of tickets) {
        if (typeof ticket.subject === 'string' && ticket.subject.startsWith('E2E Test')) {
          await page.request.delete(`${apiUrl}/tickets/${ticket.id}`, {
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
