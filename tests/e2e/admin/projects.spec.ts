import { test, expect } from '../fixtures/base';
import { testLabel } from '../fixtures/test-data';
import { getNonce, getApiUrl } from '../helpers/api';

test.describe.serial('Admin > Projects CRUD', () => {
  const projectName = testLabel('Project');
  const updatedName = projectName + ' Updated';

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-delivery');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });
  });

  test('can create a project and see it in the list', async ({ page, consoleErrors }) => {
    await page.getByRole('button', { name: 'Add New Project' }).click();
    await expect(page.getByRole('heading', { name: /Add New Project/i })).toBeVisible();

    // Fill required fields
    await page.getByLabel('Project Name:').fill(projectName);
    // Customer dropdown auto-selects the first available customer
    await page.getByLabel('Sold Hours:').fill('10');

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/projects') && resp.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'Save Project' }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(201);

    // Form closes and project appears in the table
    await expect(page.locator('a, button', { hasText: projectName })).toBeVisible({ timeout: 10_000 });
  });

  test('can edit a project via the kebab menu', async ({ page, consoleErrors }) => {
    const row = page.locator('tr', { hasText: projectName });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Edit', { exact: true }).click();

    await expect(page.getByRole('heading', { name: /Edit Project/i })).toBeVisible();

    const nameInput = page.getByLabel('Project Name:');
    await nameInput.clear();
    await nameInput.fill(updatedName);

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/projects/') && resp.request().method() === 'PUT'
    );
    await page.getByRole('button', { name: 'Save Project' }).click();
    await responsePromise;

    await expect(page.locator('a, button', { hasText: updatedName })).toBeVisible({ timeout: 10_000 });
  });

  test('can archive a project via kebab menu', async ({ page, consoleErrors }) => {
    page.on('dialog', (dialog) => dialog.accept());

    const row = page.locator('tr', { hasText: updatedName });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Archive', { exact: true }).click();

    const archiveResponse = await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/projects/') && resp.request().method() === 'DELETE'
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
      await page.goto('/wp-admin/admin.php?page=pet-delivery');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      if (!nonce || !apiUrl) return;

      const res = await page.request.get(`${apiUrl}/projects`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (!res.ok()) return;

      const projects = await res.json();
      for (const project of projects) {
        if (typeof project.name === 'string' && project.name.startsWith('E2E Test')) {
          await page.request.delete(`${apiUrl}/projects/${project.id}`, {
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
