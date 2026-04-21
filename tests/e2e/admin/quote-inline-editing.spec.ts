import { test, expect } from '../fixtures/base';
import { testLabel } from '../fixtures/test-data';
import { getNonce, getApiUrl } from '../helpers/api';

/**
 * E2E tests for the inline quote block editing UX.
 *
 * These tests create a quote via the API, add blocks, then exercise the new
 * inline editing UI (service block editor, project accordion, revert, keyboard
 * shortcuts).
 */
test.describe.serial('Admin > Quote Inline Editing', () => {
  let quoteId: number;
  let sectionId: number;
  let serviceBlockId: number;
  let projectBlockId: number;
  const quoteTitle = testLabel('InlineEdit');

  // ── Setup: create a quote + section + blocks via API ──────────────
  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
      await page.waitForFunction(
        () => {
          const el = document.getElementById('pet-admin-root');
          return el !== null && el.children.length > 0;
        },
        { timeout: 15_000 }
      );

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      expect(nonce).toBeTruthy();
      expect(apiUrl).toBeTruthy();
      if (!nonce || !apiUrl) {
        return;
      }

      const customersRes = await page.request.get(`${apiUrl}/customers`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      expect(customersRes.ok()).toBeTruthy();
      const customers = await customersRes.json();
      const customerId = Array.isArray(customers) && customers.length > 0 ? Number(customers[0].id) : 0;
      expect(customerId).toBeGreaterThan(0);

      // Create quote
      const quoteRes = await page.request.post(`${apiUrl}/quotes`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { customerId, title: quoteTitle },
      });
      expect(quoteRes.ok()).toBeTruthy();
      const quoteData = await quoteRes.json();
      quoteId = quoteData.id;

      // Create section
      const sectionRes = await page.request.post(
        `${apiUrl}/quotes/${quoteId}/sections`,
        {
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
          },
          data: { name: 'Services' },
        }
      );
      expect(sectionRes.ok()).toBeTruthy();
      const sectionData = await sectionRes.json();
      const sections = Array.isArray(sectionData.sections)
        ? sectionData.sections
        : [];
      sectionId = sections[sections.length - 1]?.id;

      // Add a service block
      const svcRes = await page.request.post(
        `${apiUrl}/quotes/${quoteId}/sections/${sectionId}/blocks`,
        {
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
          },
          data: {
            sectionId,
            type: 'OnceOffSimpleServiceBlock',
            payload: {
              description: 'Consulting',
              quantity: 10,
              sellValue: 150,
              totalValue: 1500,
              unit: 'hours',
            },
          },
        }
      );
      expect(svcRes.ok()).toBeTruthy();
      const svcData = await svcRes.json();
      const blocks = Array.isArray(svcData.blocks) ? svcData.blocks : [];
      serviceBlockId = blocks[blocks.length - 1]?.id;

      // Add a project block
      const projRes = await page.request.post(
        `${apiUrl}/quotes/${quoteId}/sections/${sectionId}/blocks`,
        {
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
          },
          data: {
            sectionId,
            type: 'OnceOffProjectBlock',
            payload: {
              description: 'Migration Project',
              phases: [
                {
                  id: 'phase-1',
                  name: 'Discovery',
                  order: 0,
                  units: [
                    {
                      id: 'unit-1',
                      description: 'Analysis',
                      quantity: 8,
                      unitPrice: 100,
                      totalValue: 800,
                      unit: 'hours',
                    },
                  ],
                },
              ],
              totalValue: 800,
            },
          },
        }
      );
      expect(projRes.ok()).toBeTruthy();
      const projData = await projRes.json();
      const projBlocks = Array.isArray(projData.blocks)
        ? projData.blocks
        : [];
      projectBlockId = projBlocks[projBlocks.length - 1]?.id;
    } finally {
      await context.close();
    }
  });

  // Navigate to the quote detail page before each test
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await page.waitForFunction(
      () => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      },
      { timeout: 15_000 }
    );

    // Switch to quotes tab then click our quote
    await page.getByRole('button', { name: 'Quotes' }).click();
    await page
      .locator('button, a', { hasText: quoteTitle })
      .first()
      .click({ timeout: 10_000 });

    // Wait for detail view
    await expect(
      page.getByRole('button', { name: /Back/i }).first()
    ).toBeVisible({ timeout: 10_000 });
  });

  // ── Test: service block inline editing ────────────────────────────
  test('can open and edit a service block inline', async ({
    page,
    consoleErrors,
  }) => {
    // The service block row should be visible with the description
    const row = page.locator('tr', { hasText: 'Consulting' });
    await expect(row).toBeVisible({ timeout: 10_000 });

    // Click to edit
    await row.click();

    // The inline editor should appear with description input pre-filled
    const descInput = page.locator(
      'input[type="text"][placeholder="Description"]'
    );
    await expect(descInput).toBeVisible({ timeout: 5_000 });
    await expect(descInput).toHaveValue('Consulting');

    // Change the description
    await descInput.fill('Consulting v2');

    // The save button (✓) should be visible
    const saveBtn = page.locator(
      'button.button-primary',
      { hasText: '✓' }
    );
    await expect(saveBtn).toBeVisible();

    // Save — wait for the PATCH
    const patchPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/blocks/${serviceBlockId}`) &&
        resp.request().method() === 'PATCH'
    );
    await saveBtn.click();
    const patchResp = await patchPromise;
    expect(patchResp.ok()).toBeTruthy();

    // After save, should be back in read mode with new description
    await expect(page.locator('td', { hasText: 'Consulting v2' })).toBeVisible({
      timeout: 5_000,
    });
  });

  // ── Test: project block accordion visibility ──────────────────────
  test('project block row renders with summary metadata', async ({
    page,
    consoleErrors,
  }) => {
    // Project block row should show the summary chip
    const projRow = page.locator('tr', { hasText: 'Migration Project' });
    await expect(projRow).toBeVisible({ timeout: 10_000 });

    // Summary chip should show phase/unit info
    await expect(
      projRow.locator('text=/1 phase/i')
    ).toBeVisible();

    await expect(page.locator('text=Migration Project').first()).toBeVisible({ timeout: 5_000 });
  });

  // ── Test: keyboard Enter opens edit mode ──────────────────────────
  test('Enter key opens edit mode on a block row', async ({
    page,
    consoleErrors,
  }) => {
    // Focus the service block row
    const row = page.locator('tr', { hasText: 'Consulting' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });
    await row.focus();

    // Press Enter
    await page.keyboard.press('Enter');

    // The inline editor description input should appear
    const descInput = page.locator(
      'input[type="text"][placeholder="Description"]'
    );
    await expect(descInput).toBeVisible({ timeout: 5_000 });

    // Press Escape to cancel
    await page.keyboard.press('Escape');

    // Editor should close — description input should be gone
    await expect(descInput).toBeHidden({ timeout: 5_000 });
  });

  // ── Test: section header shows metrics ────────────────────────────
  test('section header shows item count and percentages', async ({
    page,
    consoleErrors,
  }) => {
    // Section header "Services" should exist
    const header = page.locator('div', { hasText: 'Services' }).filter({
      has: page.locator('span', { hasText: /% of quote/ }),
    });
    await expect(header.first()).toBeVisible({ timeout: 10_000 });
  });

  // ── Cleanup ───────────────────────────────────────────────────────
  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
      await page.waitForFunction(
        () => {
          const el = document.getElementById('pet-admin-root');
          return el !== null && el.children.length > 0;
        },
        { timeout: 15_000 }
      );

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      if (!nonce || !apiUrl) return;

      // Delete the test quote
      if (quoteId) {
        await page.request.delete(`${apiUrl}/quotes/${quoteId}`, {
          headers: { 'X-WP-Nonce': nonce },
        });
      }
    } catch {
      // best-effort cleanup
    } finally {
      await context.close();
    }
  });
});
