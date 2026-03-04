import { test, expect } from '../fixtures/base';
import { testLabel } from '../fixtures/test-data';
import { getNonce, getApiUrl } from '../helpers/api';

test.describe.serial('Admin > Quotes CRUD', () => {
  const quoteTitle = testLabel('Quote');

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });

    // Commercial component defaults to the Leads tab; switch to Quotes
    await page.getByRole('button', { name: 'Quotes' }).click();
  });

  test('can create a quote and see its details', async ({ page, consoleErrors }) => {
    // Open the quote creation form
    await page.getByRole('button', { name: 'Start building quote' }).click();
    await expect(page.getByRole('heading', { name: /Step 1: Create Quote Header/i })).toBeVisible();

    // Wait for the customer dropdown to populate (auto-selects the first customer)
    await page.waitForFunction(
      () => {
        const sel = document.getElementById('pet-quote-customer') as HTMLSelectElement | null;
        return sel !== null && sel.options.length > 1 && sel.value !== '';
      },
      { timeout: 15_000 }
    );

    await page.getByLabel('Quote Title').fill(quoteTitle);

    // Submit and wait for the POST
    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/quotes') && resp.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'Start building quote' }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(201);

    // After creation the UI transitions to QuoteDetails — "Back" button should appear
    await expect(page.getByRole('button', { name: /Back/i }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('can return to the quotes list and see the new quote', async ({ page, consoleErrors }) => {
    // The quote was created in the previous test; navigate fresh and look for it in the list
    await expect(page.locator('button, a', { hasText: quoteTitle })).toBeVisible({ timeout: 10_000 });
  });

  test('can archive a quote via kebab menu', async ({ page, consoleErrors }) => {
    page.on('dialog', (dialog) => dialog.accept());

    const row = page.locator('tr', { hasText: quoteTitle });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Archive', { exact: true }).click();

    await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/quotes/') && resp.request().method() === 'DELETE'
    );

    await expect(page.locator('button, a', { hasText: quoteTitle })).toBeHidden({ timeout: 5_000 });
  });

  // Cleanup: remove any leftover E2E quote records
  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      if (!nonce || !apiUrl) return;

      const res = await page.request.get(`${apiUrl}/quotes`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (!res.ok()) return;

      const quotes = await res.json();
      for (const quote of quotes) {
        if (typeof quote.title === 'string' && quote.title.startsWith('E2E Test')) {
          await page.request.delete(`${apiUrl}/quotes/${quote.id}`, {
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
