/**
 * Portal employee page tests
 *
 * Covers the full-page detail pattern implemented in Phase 1:
 *   - List view: table, filter tabs, search, KPI strip
 *   - Detail view navigation (click row → #employees/:id)
 *   - 6-tab layout: Identity, Organisation, Roles, Skills, Certifications, Reviews
 *   - Provision modal (HR/Manager only)
 *   - Role gating: Sales cannot access employees (tested in role-gates.spec.ts)
 *
 * Tests run as HR (can edit) and Manager (can edit + assign roles).
 */
import type { Page } from '@playwright/test';
import { test, expect, PORTAL_AUTH } from './fixtures/portal-base';

// ── Shared helpers ────────────────────────────────────────────────────────────

async function goToEmployees(page: Page) {
  // Navigate to portal and click the Employees nav item
  await page.goto('/portal#employees');
  await page.waitForSelector('nav.portal-sidebar', { timeout: 15_000 });
  // Wait for the page title to appear
  await expect(page.locator('.portal-page-title')).toContainText('Employees', { timeout: 10_000 });
}

// ── HR role ───────────────────────────────────────────────────────────────────

test.describe('Employees — HR role', () => {
  test.use({ storageState: PORTAL_AUTH.hr });

  test('list view renders KPI strip and table', async ({ portalReady, consoleErrors }) => {
    await goToEmployees(portalReady);

    // KPI strip
    await expect(portalReady.locator('.portal-kpi-card').first()).toBeVisible();

    // Filter tabs
    await expect(portalReady.locator('.portal-filter-tab', { hasText: 'Active' })).toBeVisible();
    await expect(portalReady.locator('.portal-filter-tab', { hasText: 'All' })).toBeVisible();
    await expect(portalReady.locator('.portal-filter-tab', { hasText: 'Archived' })).toBeVisible();

    // Either table rows or empty state — not an error state
    const hasTable = await portalReady.locator('.portal-card table').isVisible().catch(() => false);
    const hasEmpty = await portalReady.locator('.portal-empty').isVisible().catch(() => false);
    expect(hasTable || hasEmpty).toBe(true);

    void consoleErrors;
  });

  test('New Employee button is visible for HR', async ({ portalReady }) => {
    await goToEmployees(portalReady);
    await expect(portalReady.locator('.portal-btn-primary', { hasText: 'New Employee' })).toBeVisible();
  });

  test('provision modal opens and closes', async ({ portalReady, consoleErrors }) => {
    await goToEmployees(portalReady);

    await portalReady.locator('.portal-btn-primary', { hasText: 'New Employee' }).click();

    // Modal should appear
    await expect(portalReady.locator('.portal-modal')).toBeVisible();
    await expect(portalReady.locator('.portal-modal-title')).toContainText('New Employee');

    // Required fields are present
    await expect(portalReady.locator('.portal-modal input[type="text"]').first()).toBeVisible();
    await expect(portalReady.locator('.portal-modal input[type="email"]')).toBeVisible();

    // Close via × button
    await portalReady.locator('.portal-modal-close').click();
    await expect(portalReady.locator('.portal-modal')).not.toBeVisible();

    void consoleErrors;
  });

  test('clicking an employee row navigates to detail view', async ({ portalReady, consoleErrors }) => {
    await goToEmployees(portalReady);

    // Only proceed if there are employees in the list
    const rowCount = await portalReady.locator('.portal-card table tbody tr').count();
    if (rowCount === 0) {
      test.info().annotations.push({ type: 'skip-reason', description: 'No employees to click' });
      return;
    }

    // Click the first "View →" button
    await portalReady.locator('.portal-card table tbody tr').first().locator('.portal-btn-sm').click();

    // URL hash should change to #employees/:id
    await portalReady.waitForFunction(() => /^#employees\/\d+$/.test(window.location.hash));

    // Detail header should render
    await expect(portalReady.locator('.portal-detail-header')).toBeVisible();
    await expect(portalReady.locator('.portal-detail-back')).toContainText('Employees');

    void consoleErrors;
  });

  test('detail view shows all 6 tabs', async ({ portalReady, consoleErrors }) => {
    await goToEmployees(portalReady);

    const rowCount = await portalReady.locator('.portal-card table tbody tr').count();
    if (rowCount === 0) return;

    await portalReady.locator('.portal-card table tbody tr').first().click();
    await portalReady.waitForFunction(() => /^#employees\/\d+$/.test(window.location.hash));
    await expect(portalReady.locator('.portal-tab-bar')).toBeVisible();

    const expectedTabs = ['Identity', 'Organisation', 'Roles', 'Skills', 'Certifications', 'Reviews'];
    for (const label of expectedTabs) {
      await expect(portalReady.locator('.portal-tab', { hasText: label })).toBeVisible();
    }

    void consoleErrors;
  });

  test('Identity tab shows employee info', async ({ portalReady }) => {
    await goToEmployees(portalReady);

    const rowCount = await portalReady.locator('.portal-card table tbody tr').count();
    if (rowCount === 0) return;

    await portalReady.locator('.portal-card table tbody tr').first().click();
    await portalReady.waitForFunction(() => /^#employees\/\d+$/.test(window.location.hash));

    // Identity tab is active by default
    await expect(portalReady.locator('.portal-tab.active')).toContainText('Identity');

    // Info grid should render
    await expect(portalReady.locator('.portal-info-grid')).toBeVisible();

    // Edit button is visible for HR
    await expect(portalReady.locator('.portal-section-card-header .portal-btn-sm', { hasText: 'Edit' })).toBeVisible();
  });

  test('Organisation tab renders without error', async ({ portalReady, consoleErrors }) => {
    await goToEmployees(portalReady);

    const rowCount = await portalReady.locator('.portal-card table tbody tr').count();
    if (rowCount === 0) return;

    await portalReady.locator('.portal-card table tbody tr').first().click();
    await portalReady.waitForFunction(() => /^#employees\/\d+$/.test(window.location.hash));

    await portalReady.locator('.portal-tab', { hasText: 'Organisation' }).click();
    await expect(portalReady.locator('.portal-tab.active')).toContainText('Organisation');

    // Should render a section card (reporting line)
    await expect(portalReady.locator('.portal-section-card').first()).toBeVisible({ timeout: 5_000 });

    void consoleErrors;
  });

  test('back button returns to list', async ({ portalReady }) => {
    await goToEmployees(portalReady);

    const rowCount = await portalReady.locator('.portal-card table tbody tr').count();
    if (rowCount === 0) return;

    await portalReady.locator('.portal-card table tbody tr').first().click();
    await portalReady.waitForFunction(() => /^#employees\/\d+$/.test(window.location.hash));

    await portalReady.locator('.portal-detail-back').click();
    await portalReady.waitForFunction(() => window.location.hash === '#employees');

    // List title should be visible again
    await expect(portalReady.locator('.portal-page-title')).toContainText('Employees');
  });
});

// ── Manager role ──────────────────────────────────────────────────────────────

test.describe('Employees — Manager role', () => {
  test.use({ storageState: PORTAL_AUTH.manager });

  test('provision modal shows Portal Role field for Manager', async ({ portalReady, consoleErrors }) => {
    await goToEmployees(portalReady);

    await portalReady.locator('.portal-btn-primary', { hasText: 'New Employee' }).click();
    await expect(portalReady.locator('.portal-modal')).toBeVisible();

    // Portal Role select should be visible for managers but not for HR
    await expect(portalReady.locator('.portal-modal select').last()).toBeVisible();
    // The last select contains the PORTAL_ROLES options
    await expect(portalReady.locator('.portal-modal', { hasText: 'Portal Role' })).toBeVisible();

    await portalReady.locator('.portal-modal-close').click();
    void consoleErrors;
  });

  test('Skills and Certifications tabs load without error', async ({ portalReady, consoleErrors }) => {
    await goToEmployees(portalReady);

    const rowCount = await portalReady.locator('.portal-card table tbody tr').count();
    if (rowCount === 0) return;

    await portalReady.locator('.portal-card table tbody tr').first().click();
    await portalReady.waitForFunction(() => /^#employees\/\d+$/.test(window.location.hash));

    // Skills tab
    await portalReady.locator('.portal-tab', { hasText: 'Skills' }).click();
    await portalReady.waitForTimeout(500); // allow async fetch
    // Should show either data list or empty state — not a crashed component
    const hasSkillsContent = await portalReady.locator('.portal-data-list, .portal-empty').first().isVisible().catch(() => false);
    expect(hasSkillsContent).toBe(true);

    // Certifications tab
    await portalReady.locator('.portal-tab', { hasText: 'Certifications' }).click();
    await portalReady.waitForTimeout(500);
    const hasCertsContent = await portalReady.locator('.portal-data-list, .portal-empty').first().isVisible().catch(() => false);
    expect(hasCertsContent).toBe(true);

    void consoleErrors;
  });
});
