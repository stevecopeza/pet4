import { test, expect } from '../fixtures/base';
import { testLabel } from '../fixtures/test-data';
import { getNonce, getApiUrl } from '../helpers/api';

test.describe.serial('Admin > Knowledge Articles CRUD', () => {
  const articleTitle = testLabel('Article');
  const updatedTitle = articleTitle + ' Updated';

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-knowledge');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });
  });

  test('can create an article and see it in the list', async ({ page, consoleErrors }) => {
    await page.getByRole('button', { name: 'Create New Article' }).click();
    await expect(page.getByRole('heading', { name: /Create New Article/i })).toBeVisible();

    await page.getByLabel('Title:').fill(articleTitle);
    await page.getByLabel('Content:').fill('E2E test article body. This can be deleted.');
    // Category defaults to "general", status defaults to "draft" — no changes needed

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/articles') && resp.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'Create Article' }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(201);

    // Form closes and article appears in the table
    await expect(page.locator('td', { hasText: articleTitle })).toBeVisible({ timeout: 10_000 });
  });

  test('can edit an article via kebab menu', async ({ page, consoleErrors }) => {
    const row = page.locator('tr', { hasText: articleTitle });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Edit', { exact: true }).click();

    await expect(page.getByRole('heading', { name: /Edit Article/i })).toBeVisible();

    const titleInput = page.getByLabel('Title:');
    await titleInput.clear();
    await titleInput.fill(updatedTitle);

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/articles/') && resp.request().method() === 'PUT'
    );
    await page.getByRole('button', { name: 'Update Article' }).click();
    await responsePromise;

    // Updated title should appear in the table
    await expect(page.locator('td', { hasText: updatedTitle })).toBeVisible({ timeout: 10_000 });
  });

  test('can archive an article via kebab menu', async ({ page, consoleErrors }) => {

    const row = page.locator('tr', { hasText: updatedTitle });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Archive', { exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Archive' }).click();

    await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/articles/') && resp.request().method() === 'DELETE'
    );

    // After archiving, the Edit option in the kebab is disabled and Archive is removed.
    // If the row is still visible (archived items are shown with a badge), verify Archive is gone.
    const archivedRow = page.locator('tr', { hasText: updatedTitle });
    if (await archivedRow.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await archivedRow.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
      await expect(page.getByText('Archive', { exact: true })).toBeHidden();
    }
    // If the row is gone entirely, the archive succeeded — test passes.
  });

  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-knowledge');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      if (!nonce || !apiUrl) return;

      const res = await page.request.get(`${apiUrl}/articles`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (!res.ok()) return;

      const articles = await res.json();
      for (const article of articles) {
        if (typeof article.title === 'string' && article.title.startsWith('E2E Test')) {
          await page.request.delete(`${apiUrl}/articles/${article.id}`, {
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
