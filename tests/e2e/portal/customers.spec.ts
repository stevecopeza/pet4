/**
 * Portal customer page tests
 *
 * Covers the full-page detail pattern applied in Phase 2:
 *   - List view: table, filter tabs, search, KPI strip
 *   - Detail view navigation (click row → #customers/:id)
 *   - 2-tab layout: Overview, Contacts
 *   - Create / Edit modal
 *   - Row click navigates to full-page detail
 */
import type { Page } from '@playwright/test';
import { test, expect, PORTAL_AUTH } from './fixtures/portal-base';

// ── Shared helpers ────────────────────────────────────────────────────────────

async function goToCustomers(page: Page) {
  await page.goto('/portal#customers');
  await page.waitForSelector('nav.portal-sidebar', { timeout: 15_000 });
  await expect(page.locator('.portal-page-title')).toContainText('Customers', {
    timeout: 10_000,
  });
}

// ── Sales role ────────────────────────────────────────────────────────────────

test.describe('Customers — Sales role', () => {
  test.use({ storageState: PORTAL_AUTH.sales });

  test('list view renders KPI strip, filter tabs and table', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToCustomers(portalReady);

    // KPI strip
    await expect(portalReady.locator('.portal-kpi-card').first()).toBeVisible();

    // Filter tabs
    await expect(
      portalReady.locator('.portal-filter-tab', { hasText: 'All' })
    ).toBeVisible();
    await expect(
      portalReady.locator('.portal-filter-tab', { hasText: 'Active' })
    ).toBeVisible();
    await expect(
      portalReady.locator('.portal-filter-tab', { hasText: 'Archived' })
    ).toBeVisible();

    // Table or empty state — not an error state
    const hasTable = await portalReady
      .locator('.portal-card table')
      .isVisible()
      .catch(() => false);
    const hasEmpty = await portalReady
      .locator('.portal-empty')
      .isVisible()
      .catch(() => false);
    expect(hasTable || hasEmpty).toBe(true);

    void consoleErrors;
  });

  test('New Customer button is visible', async ({ portalReady }) => {
    await goToCustomers(portalReady);
    await expect(
      portalReady.locator('.portal-btn-primary', { hasText: 'New Customer' })
    ).toBeVisible();
  });

  test('clicking a row navigates to #customers/:id', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToCustomers(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) {
      test
        .info()
        .annotations.push({
          type: 'skip-reason',
          description: 'No customers to click',
        });
      return;
    }

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();

    // URL hash changes
    await portalReady.waitForFunction(() =>
      /^#customers\/\d+$/.test(window.location.hash)
    );

    // Detail header renders
    await expect(portalReady.locator('.portal-detail-header')).toBeVisible();
    await expect(portalReady.locator('.portal-detail-back')).toContainText(
      'Customers'
    );

    void consoleErrors;
  });

  test('detail view shows Overview and Contacts tabs', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToCustomers(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#customers\/\d+$/.test(window.location.hash)
    );

    await expect(portalReady.locator('.portal-tab-bar')).toBeVisible();
    await expect(
      portalReady.locator('.portal-tab', { hasText: 'Overview' })
    ).toBeVisible();
    await expect(
      portalReady.locator('.portal-tab', { hasText: 'Contacts' })
    ).toBeVisible();

    void consoleErrors;
  });

  test('Overview tab shows info grid', async ({ portalReady }) => {
    await goToCustomers(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#customers\/\d+$/.test(window.location.hash)
    );

    // Overview tab should be active by default
    await expect(portalReady.locator('.portal-tab.active')).toContainText(
      'Overview'
    );
    await expect(portalReady.locator('.portal-info-grid')).toBeVisible();
  });

  test('Contacts tab renders without error', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToCustomers(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#customers\/\d+$/.test(window.location.hash)
    );

    await portalReady.locator('.portal-tab', { hasText: 'Contacts' }).click();
    await expect(portalReady.locator('.portal-tab.active')).toContainText(
      'Contacts'
    );

    // Either data list or empty state — not crashed
    await portalReady.waitForTimeout(500);
    const hasContent = await portalReady
      .locator('.portal-data-list, .portal-empty')
      .first()
      .isVisible()
      .catch(() => false);
    expect(hasContent).toBe(true);

    void consoleErrors;
  });

  test('back button returns to list', async ({ portalReady }) => {
    await goToCustomers(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#customers\/\d+$/.test(window.location.hash)
    );

    await portalReady.locator('.portal-detail-back').click();
    await portalReady.waitForFunction(
      () => window.location.hash === '#customers'
    );

    await expect(portalReady.locator('.portal-page-title')).toContainText(
      'Customers'
    );
  });
});

// ── HR role ───────────────────────────────────────────────────────────────────

test.describe('Customers — HR role', () => {
  test.use({ storageState: PORTAL_AUTH.hr });

  test('create modal opens and closes', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToCustomers(portalReady);

    await portalReady
      .locator('.portal-btn-primary', { hasText: 'New Customer' })
      .click();

    await expect(portalReady.locator('.portal-modal')).toBeVisible();
    await expect(portalReady.locator('.portal-modal-title')).toContainText(
      'New Customer'
    );

    // Required fields present
    await expect(
      portalReady.locator('.portal-modal input[type="text"]').first()
    ).toBeVisible();
    await expect(
      portalReady.locator('.portal-modal input[type="email"]')
    ).toBeVisible();

    // Close via × button
    await portalReady.locator('.portal-modal-close').click();
    await expect(portalReady.locator('.portal-modal')).not.toBeVisible();

    void consoleErrors;
  });

  test('Edit button opens edit modal from detail view', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToCustomers(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#customers\/\d+$/.test(window.location.hash)
    );

    // Click Edit in the detail header
    await portalReady
      .locator('.portal-detail-header .portal-btn-sm', { hasText: 'Edit' })
      .click();

    await expect(portalReady.locator('.portal-modal')).toBeVisible();
    await expect(portalReady.locator('.portal-modal-title')).toContainText(
      'Edit Customer'
    );

    await portalReady.locator('.portal-modal-close').click();
    await expect(portalReady.locator('.portal-modal')).not.toBeVisible();

    void consoleErrors;
  });
});
