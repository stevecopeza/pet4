/**
 * Portal quotes page tests
 *
 * Covers the full-page detail pattern applied in Phase 2:
 *   - List view: table, filter tabs, search, KPI strip, approval banner
 *   - Detail view navigation (click row / View → → #quotes/:id)
 *   - Quote detail: info grid, builder link, PDF link, action buttons
 *   - Create modal
 *   - Role-specific: Manager sees approve/reject; Sales sees submit button
 */
import type { Page } from '@playwright/test';
import { test, expect, PORTAL_AUTH } from './fixtures/portal-base';

// ── Shared helpers ────────────────────────────────────────────────────────────

async function goToQuotes(page: Page) {
  await page.goto('/portal#quotes');
  await page.waitForSelector('nav.portal-sidebar', { timeout: 15_000 });
  await expect(page.locator('.portal-page-title')).toContainText('Quotes', {
    timeout: 10_000,
  });
}

// ── Sales role ────────────────────────────────────────────────────────────────

test.describe('Quotes — Sales role', () => {
  test.use({ storageState: PORTAL_AUTH.sales });

  test('list view renders KPI strip, filter tabs and table', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    // KPI strip
    await expect(portalReady.locator('.portal-kpi-card').first()).toBeVisible();

    // Filter tabs
    for (const label of ['All', 'Draft', 'Pending', 'Approved', 'Sent', 'Accepted']) {
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

  test('New Quote button is visible', async ({ portalReady }) => {
    await goToQuotes(portalReady);
    await expect(
      portalReady.locator('.portal-btn-primary', { hasText: 'New Quote' })
    ).toBeVisible();
  });

  test('create modal opens and closes', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    await portalReady
      .locator('.portal-btn-primary', { hasText: 'New Quote' })
      .click();

    await expect(portalReady.locator('.portal-modal')).toBeVisible();
    await expect(portalReady.locator('.portal-modal-title')).toContainText(
      'New Quote'
    );

    // Customer select and title field present
    await expect(
      portalReady.locator('.portal-modal select').first()
    ).toBeVisible();
    await expect(
      portalReady.locator('.portal-modal input[type="text"]').first()
    ).toBeVisible();

    await portalReady.locator('.portal-modal-close').click();
    await expect(portalReady.locator('.portal-modal')).not.toBeVisible();

    void consoleErrors;
  });

  test('"View →" button navigates to #quotes/:id', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) {
      test
        .info()
        .annotations.push({
          type: 'skip-reason',
          description: 'No quotes to click',
        });
      return;
    }

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .locator('.portal-btn-sm')
      .click();

    await portalReady.waitForFunction(() =>
      /^#quotes\/\d+$/.test(window.location.hash)
    );

    await expect(portalReady.locator('.portal-detail-header')).toBeVisible();
    await expect(portalReady.locator('.portal-detail-back')).toContainText(
      'Quotes'
    );

    void consoleErrors;
  });

  test('row click also navigates to #quotes/:id', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();

    await portalReady.waitForFunction(() =>
      /^#quotes\/\d+$/.test(window.location.hash)
    );

    await expect(portalReady.locator('.portal-detail-header')).toBeVisible();

    void consoleErrors;
  });

  test('detail view shows info grid and builder link', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#quotes\/\d+$/.test(window.location.hash)
    );

    await expect(portalReady.locator('.portal-section-card')).toBeVisible();
    await expect(portalReady.locator('.portal-info-grid')).toBeVisible();

    // Builder link
    await expect(
      portalReady.locator('a', { hasText: 'Open Quote Builder' })
    ).toBeVisible();

    void consoleErrors;
  });

  test('draft quote shows "Submit for Approval" button', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    // Navigate to a draft quote specifically
    const draftRow = portalReady.locator(
      '.portal-card table tbody tr:has(.portal-badge-draft)'
    );
    const draftCount = await draftRow.count();
    if (draftCount === 0) {
      test
        .info()
        .annotations.push({
          type: 'skip-reason',
          description: 'No draft quotes in test data',
        });
      return;
    }

    await draftRow.first().click();
    await portalReady.waitForFunction(() =>
      /^#quotes\/\d+$/.test(window.location.hash)
    );

    await expect(
      portalReady.locator('button', { hasText: 'Submit for Approval' })
    ).toBeVisible();

    void consoleErrors;
  });

  test('back button returns to list', async ({ portalReady }) => {
    await goToQuotes(portalReady);

    const rowCount = await portalReady
      .locator('.portal-card table tbody tr')
      .count();
    if (rowCount === 0) return;

    await portalReady
      .locator('.portal-card table tbody tr')
      .first()
      .click();
    await portalReady.waitForFunction(() =>
      /^#quotes\/\d+$/.test(window.location.hash)
    );

    await portalReady.locator('.portal-detail-back').click();
    await portalReady.waitForFunction(
      () => window.location.hash === '#quotes'
    );

    await expect(portalReady.locator('.portal-page-title')).toContainText(
      'Quotes'
    );
  });
});

// ── Manager role ──────────────────────────────────────────────────────────────

test.describe('Quotes — Manager role', () => {
  test.use({ storageState: PORTAL_AUTH.manager });

  test('approval banner appears when pending quotes exist', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    const kpiPending = parseInt(
      (await portalReady
        .locator('.portal-kpi-card', { hasText: 'Pending Approval' })
        .locator('.portal-kpi-value')
        .textContent()) ?? '0',
      10
    );

    if (kpiPending > 0) {
      await expect(portalReady.locator('.portal-banner-amber')).toBeVisible();
      await expect(
        portalReady.locator('.portal-banner-amber')
      ).toContainText('awaiting your approval');
    }

    void consoleErrors;
  });

  test('pending quote shows Approve and Reject buttons', async ({
    portalReady,
    consoleErrors,
  }) => {
    await goToQuotes(portalReady);

    const pendingRow = portalReady.locator(
      '.portal-card table tbody tr:has(.portal-badge-pending)'
    );
    const pendingCount = await pendingRow.count();
    if (pendingCount === 0) {
      test
        .info()
        .annotations.push({
          type: 'skip-reason',
          description: 'No pending quotes in test data',
        });
      return;
    }

    await pendingRow.first().click();
    await portalReady.waitForFunction(() =>
      /^#quotes\/\d+$/.test(window.location.hash)
    );

    await expect(
      portalReady.locator('button', { hasText: 'Approve' })
    ).toBeVisible();
    await expect(
      portalReady.locator('button', { hasText: 'Reject' })
    ).toBeVisible();

    void consoleErrors;
  });
});
