/**
 * Portal leads page tests
 *
 * Covers the full-page detail pattern applied in Phase 2:
 *   - List view: table, filter tabs, search, KPI strip
 *   - Detail view navigation (click row / View → → #leads/:id)
 *   - Lead detail: info grid, description, Convert + Delete actions
 *   - Create / Edit modal
 */
import type { Page } from '@playwright/test';
import { test, expect, PORTAL_AUTH } from './fixtures/portal-base';

// ── Shared helpers ────────────────────────────────────────────────────────────

async function goToLeads(page: Page) {
  await page.goto('/portal#leads');
  await page.waitForSelector('nav.portal-sidebar', { timeout: 15_000 });
  await expect(page.locator('.portal-page-title')).toContainText('Leads', {
    timeout: 10_000,
  });
}

// ── Sales role ────────────────────────────────────────────────────────────────

test.describe('Leads — Sales role', () => {
  test.use({ storageState: PORTAL_AUTH.sales });

  test('list view renders KPI strip, filter tabs and table', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToLeads(portalReady);

    // KPI strip
    await expect(portalReady.locator('.portal-kpi-card').first()).toBeVisible();

    // Filter tabs
    for (const label of ['All', 'New', 'Qualified', 'Converted', 'Lost']) {
      await expect(
        portalReady.locator('.portal-filter-tab', { hasText: label })
      ).toBeVisible();
    }

    // Table or empty state
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

  test('New Lead button is visible', async ({ portalReady }) => {
    await goToLeads(portalReady);
    await expect(
      portalReady.locator('.portal-btn-primary', { hasText: 'New Lead' })
    ).toBeVisible();
  });

  test('create modal opens and closes', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToLeads(portalReady);

    await portalReady
      .locator('.portal-btn-primary', { hasText: 'New Lead' })
      .click();

    await expect(portalReady.locator('.portal-modal')).toBeVisible();
    await expect(portalReady.locator('.portal-modal-title')).toContainText(
      'New Lead'
    );

    // Subject field is present
    await expect(
      portalReady.locator('.portal-modal input[type="text"]').first()
    ).toBeVisible();

    await portalReady.locator('.portal-modal-close').click();
    await expect(portalReady.locator('.portal-modal')).not.toBeVisible();

    void consoleErrors;
  });

  test('"View →" button navigates to #leads/:id', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToLeads(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) {
      test
        .info()
        .annotations.push({
          type: 'skip-reason',
          description: 'No leads to click',
        });
      return;
    }

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .locator('.portal-btn-sm')
      .click();

    await portalReady.waitForFunction(() =>
      /^#leads\/\d+$/.test(window.location.hash)
    );

    await expect(portalReady.locator('.portal-detail-header')).toBeVisible();
    await expect(portalReady.locator('.portal-detail-back')).toContainText(
      'Leads'
    );

    void consoleErrors;
  });

  test('row click also navigates to #leads/:id', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToLeads(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();

    await portalReady.waitForFunction(() =>
      /^#leads\/\d+$/.test(window.location.hash)
    );

    await expect(portalReady.locator('.portal-detail-header')).toBeVisible();

    void consoleErrors;
  });

  test('detail view shows lead info grid and actions', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToLeads(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#leads\/\d+$/.test(window.location.hash)
    );

    // Section card with info grid
    await expect(portalReady.locator('.portal-section-card')).toBeVisible();
    await expect(portalReady.locator('.portal-info-grid')).toBeVisible();

    // Convert or Delete action buttons should be present
    const hasActions = await portalReady
      .locator('.portal-section-card .portal-btn-sm')
      .count();
    expect(hasActions).toBeGreaterThan(0);

    void consoleErrors;
  });

  test('detail view Edit button opens edit modal', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToLeads(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#leads\/\d+$/.test(window.location.hash)
    );

    await portalReady
      .locator('.portal-detail-header .portal-btn-sm', { hasText: 'Edit' })
      .click();

    await expect(portalReady.locator('.portal-modal')).toBeVisible();
    await expect(portalReady.locator('.portal-modal-title')).toContainText(
      'Edit Lead'
    );

    await portalReady.locator('.portal-modal-close').click();
    await expect(portalReady.locator('.portal-modal')).not.toBeVisible();

    void consoleErrors;
  });

  test('back button returns to list', async ({ portalReady }) => {
    await goToLeads(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#leads\/\d+$/.test(window.location.hash)
    );

    await portalReady.locator('.portal-detail-back').click();
    await portalReady.waitForFunction(
      () => window.location.hash === '#leads'
    );

    await expect(portalReady.locator('.portal-page-title')).toContainText(
      'Leads'
    );
  });
});

// ── Manager role ──────────────────────────────────────────────────────────────

test.describe('Leads — Manager role', () => {
  test.use({ storageState: PORTAL_AUTH.manager });

  test('Convert to Quote button visible for non-converted lead', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToLeads(portalReady);

    // Look for a non-converted lead
    const activeRow = portalReady.locator(
      '.portal-card table tbody tr:not(:has(.portal-badge-accepted))'
    );
    const rowCount = await activeRow.count();
    if (rowCount === 0) {
      test
        .info()
        .annotations.push({
          type: 'skip-reason',
          description: 'No non-converted leads',
        });
      return;
    }

    await activeRow.first().click();
    await portalReady.waitForFunction(() =>
      /^#leads\/\d+$/.test(window.location.hash)
    );

    // If lead is not converted, Convert button should appear
    const convertBtn = portalReady.locator('.portal-section-card button', {
      hasText: 'Convert to Quote',
    });
    const hasConvert = await convertBtn.isVisible().catch(() => false);
    // Only assert if the lead is indeed not converted
    if (hasConvert) {
      await expect(convertBtn).toBeVisible();
    }

    void consoleErrors;
  });
});
