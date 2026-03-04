import { test, expect } from '../fixtures/base';
import { testLabel } from '../fixtures/test-data';
import { getNonce, getApiUrl } from '../helpers/api';

test.describe.serial('Admin > Customers CRUD', () => {
  const customerName = testLabel('Customer');
  const customerEmail = 'e2e-test@example.com';
  const updatedName = customerName + ' Updated';

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-crm');
    // Wait for React to mount and customers to load
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });
  });

  test('can create a customer and see it in the list', async ({ page, consoleErrors }) => {
    // Click "Add New Customer"
    await page.getByRole('button', { name: 'Add New Customer' }).click();

    // Fill the form
    await page.getByLabel('Name:', { exact: true }).fill(customerName);
    await page.getByLabel('Contact Email:').fill(customerEmail);

    // Submit and wait for the API response
    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/customers') && resp.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'Create Customer' }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(201);

    // The form should close and the customer should appear in the table
    await expect(page.getByRole('button', { name: customerName })).toBeVisible();
  });

  test('can edit a customer', async ({ page, consoleErrors }) => {
    // Find the customer row and click its name to open edit form
    await page.getByRole('button', { name: customerName }).click();

    // Update the name
    const nameInput = page.getByLabel('Name:', { exact: true });
    await nameInput.clear();
    await nameInput.fill(updatedName);

    // Submit and wait for the API response
    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/customers/') && resp.request().method() === 'PUT'
    );
    await page.getByRole('button', { name: 'Update Customer' }).click();
    await responsePromise;

    // The updated name should appear in the table
    await expect(page.getByRole('button', { name: updatedName })).toBeVisible();
  });

  test('can archive a customer via kebab menu', async ({ page, consoleErrors }) => {
    // Accept the confirmation dialog before triggering it
    page.on('dialog', (dialog) => dialog.accept());

    // Find the row with our test customer
    const row = page.locator('tr', { hasText: updatedName });
    await expect(row).toBeVisible();

    // Open the kebab menu for this row and click Archive
    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Archive', { exact: true }).click();

    // Wait for the DELETE API call
    const archiveResponse = await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/customers/') && resp.request().method() === 'DELETE'
    );

    // Verify the DELETE response was successful
    expect(archiveResponse.ok()).toBeTruthy();
  });

  // Cleanup: if tests above fail, ensure test data is removed via API
  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-crm');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);

      if (!nonce || !apiUrl) return;

      // Fetch all customers and archive any E2E test leftovers
      const response = await page.request.get(`${apiUrl}/customers`, {
        headers: { 'X-WP-Nonce': nonce },
      });

      if (response.ok()) {
        const customers = await response.json();
        for (const customer of customers) {
          if (typeof customer.name === 'string' && customer.name.startsWith('E2E Test')) {
            await page.request.delete(`${apiUrl}/customers/${customer.id}`, {
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
