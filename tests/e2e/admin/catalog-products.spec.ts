import { test, expect } from '../fixtures/base';
import { testLabel } from '../fixtures/test-data';
import { getNonce, getApiUrl } from '../helpers/api';

test.describe.serial('Admin > Catalog Products CRUD', () => {
  const productName = testLabel('Product');
  const sku = `E2E-${Date.now()}`;
  const updatedName = productName + ' Updated';

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });

    // Navigate to the Products tab
    await page.getByRole('button', { name: 'Products' }).click();
    await page.waitForTimeout(500);
  });

  test('can create a catalog product', async ({ page, consoleErrors }) => {
    await page.getByRole('button', { name: 'Add Product' }).click();
    await page.getByLabel('SKU *').waitFor({ state: 'visible', timeout: 5_000 });

    await page.getByLabel('SKU *').fill(sku);
    await page.getByLabel('Name *').fill(productName);
    await page.getByLabel('Category').fill('E2E Category');
    await page.getByLabel('Unit Price *').fill('100');
    await page.getByLabel('Unit Cost').fill('60');

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/catalog-products') && resp.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'Create' }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(201);

    // Product should appear in the table
    await expect(page.locator('td', { hasText: productName })).toBeVisible();
    await expect(page.locator('td', { hasText: sku })).toBeVisible();
  });

  test('can edit a catalog product', async ({ page, consoleErrors }) => {
    // Find the row with our product and click Edit
    const row = page.locator('tr', { hasText: productName });
    await row.getByRole('button', { name: 'Edit' }).click();
    await page.getByLabel('Name *').waitFor({ state: 'visible', timeout: 5_000 });

    // Update name
    const nameInput = page.getByLabel('Name *');
    await nameInput.clear();
    await nameInput.fill(updatedName);

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/catalog-products/') && resp.request().method() === 'PUT'
    );
    await page.getByRole('button', { name: 'Update' }).click();
    await responsePromise;

    await expect(page.locator('td', { hasText: updatedName })).toBeVisible();
  });

  test('can archive a catalog product', async ({ page, consoleErrors }) => {
    page.on('dialog', (dialog) => dialog.accept());

    const row = page.locator('tr', { hasText: updatedName });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Archive' }).click();

    const archiveResponse = await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/catalog-products/') && resp.url().includes('/archive') && resp.request().method() === 'POST'
    );
    expect(archiveResponse.ok()).toBeTruthy();
  });

  // Cleanup: remove test products via API
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

      const response = await page.request.get(`${apiUrl}/catalog-products`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (response.ok()) {
        const products = await response.json();
        for (const product of products) {
          if (typeof product.name === 'string' && product.name.startsWith('E2E Test')) {
            await page.request.post(`${apiUrl}/catalog-products/${product.id}/archive`, {
              headers: { 'X-WP-Nonce': nonce },
            });
          }
        }
      }
    } catch {
      // Cleanup is best-effort
    } finally {
      await context.close();
    }
  });
});
