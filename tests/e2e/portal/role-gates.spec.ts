/**
 * Portal role-gate tests
 *
 * Verifies that each portal role sees exactly the right nav items and is
 * blocked from sections they are not permitted to access.
 *
 * Permission matrix (from PortalApp.tsx + PortalShell.tsx):
 *
 *   Section     | Sales | HR  | Manager
 *   ------------|-------|-----|--------
 *   Customers   |  ✅   | ✅  |  ✅
 *   Catalog     |  ✅   | ✅  |  ✅
 *   Leads       |  ✅   | ❌  |  ✅
 *   Quotes      |  ✅   | ❌  |  ✅
 *   Approvals   |  ❌   | ❌  |  ✅
 *   Employees   |  ❌   | ✅  |  ✅
 *
 * "Visible" → nav link is rendered in the DOM and visible.
 * "Hidden"  → nav link is not rendered at all (renderNavItem returns null).
 */
import type { Page } from '@playwright/test';
import { test, expect, PORTAL_AUTH } from './fixtures/portal-base';

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Assert a nav link IS rendered and visible. */
async function assertNavVisible(page: Page, label: string) {
  await expect(
    page.locator('.portal-nav-item', { hasText: label }),
    `Expected "${label}" nav item to be visible`
  ).toBeVisible();
}

/** Assert a nav link is NOT rendered (count === 0). */
async function assertNavAbsent(page: Page, label: string) {
  await expect(
    page.locator('.portal-nav-item', { hasText: label }),
    `Expected "${label}" nav item to be absent (not rendered)`
  ).toHaveCount(0);
}

/**
 * Assert that navigating to a hash route shows "Access Denied" for a role
 * that should not have access. This is a defence-in-depth check on top of
 * the nav gating.
 */
async function assertHashRouteBlocked(page: Page, hash: string) {
  await page.evaluate((h) => { window.location.hash = h; }, hash);
  await expect(
    page.locator('h2', { hasText: 'Access Denied' }),
    `Expected Access Denied at hash "${hash}"`
  ).toBeVisible({ timeout: 5_000 });
}

// ── Sales ─────────────────────────────────────────────────────────────────────

test.describe('Nav gates — Sales', () => {
  test.use({ storageState: PORTAL_AUTH.sales });

  test('sees Customers, Catalog, Leads, Quotes', async ({ portalReady }) => {
    await assertNavVisible(portalReady, 'Customers');
    await assertNavVisible(portalReady, 'Catalog');
    await assertNavVisible(portalReady, 'Leads');
    await assertNavVisible(portalReady, 'Quotes');
  });

  test('does not see Employees or Approvals', async ({ portalReady }) => {
    await assertNavAbsent(portalReady, 'Employees');
    await assertNavAbsent(portalReady, 'Approvals');
  });

  test('blocked from #employees route', async ({ portalReady }) => {
    await assertHashRouteBlocked(portalReady, '#employees');
  });

  test('blocked from #approvals route', async ({ portalReady }) => {
    await assertHashRouteBlocked(portalReady, '#approvals');
  });
});

// ── HR ────────────────────────────────────────────────────────────────────────

test.describe('Nav gates — HR', () => {
  test.use({ storageState: PORTAL_AUTH.hr });

  test('sees Customers, Catalog, Employees', async ({ portalReady }) => {
    await assertNavVisible(portalReady, 'Customers');
    await assertNavVisible(portalReady, 'Catalog');
    await assertNavVisible(portalReady, 'Employees');
  });

  test('does not see Leads, Quotes, or Approvals', async ({ portalReady }) => {
    await assertNavAbsent(portalReady, 'Leads');
    await assertNavAbsent(portalReady, 'Quotes');
    await assertNavAbsent(portalReady, 'Approvals');
  });

  test('blocked from #leads route', async ({ portalReady }) => {
    await assertHashRouteBlocked(portalReady, '#leads');
  });

  test('blocked from #quotes route', async ({ portalReady }) => {
    await assertHashRouteBlocked(portalReady, '#quotes');
  });

  test('blocked from #approvals route', async ({ portalReady }) => {
    await assertHashRouteBlocked(portalReady, '#approvals');
  });
});

// ── Manager ───────────────────────────────────────────────────────────────────

test.describe('Nav gates — Manager', () => {
  test.use({ storageState: PORTAL_AUTH.manager });

  test('sees all nav sections', async ({ portalReady }) => {
    await assertNavVisible(portalReady, 'Customers');
    await assertNavVisible(portalReady, 'Catalog');
    await assertNavVisible(portalReady, 'Leads');
    await assertNavVisible(portalReady, 'Quotes');
    await assertNavVisible(portalReady, 'Approvals');
    await assertNavVisible(portalReady, 'Employees');
  });

  test('can navigate to every section without Access Denied', async ({ portalReady }) => {
    const hashes = ['#customers', '#catalog', '#leads', '#quotes', '#approvals', '#employees'];

    for (const hash of hashes) {
      await portalReady.evaluate((h) => { window.location.hash = h; }, hash);
      // Wait briefly for React to re-render; confirm no "Access Denied" message
      await portalReady.waitForTimeout(300);
      await expect(
        portalReady.locator('h2', { hasText: 'Access Denied' }),
        `Unexpected "Access Denied" at hash "${hash}" for Manager`
      ).toHaveCount(0);
    }
  });
});
