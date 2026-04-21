/**
 * Portal smoke tests
 *
 * Verifies that the portal SPA:
 *   1. Mounts without JavaScript errors for each staff role
 *   2. Renders the shell (header + sidebar nav + main area)
 *   3. Shows the correct role label in the user panel
 *   4. Defaults to the Customers view (#customers hash)
 *
 * These tests use storageState from the provisioned test users created in
 * global-setup. Auth files (.auth/portal-*.json) are created by global-setup
 * and deleted by global-teardown.
 */
import type { Page } from '@playwright/test';
import { test, expect, PORTAL_AUTH } from './fixtures/portal-base';

// ── Shared shell assertions ───────────────────────────────────────────────────

async function assertShellMounted(page: Page) {
  // Header
  await expect(page.locator('.portal-header')).toBeVisible();
  await expect(page.locator('.portal-logo')).toContainText('PET Portal');

  // Sidebar nav
  await expect(page.locator('nav.portal-sidebar')).toBeVisible();

  // Main content area
  await expect(page.locator('.portal-main')).toBeVisible();
}

// ── Sales ─────────────────────────────────────────────────────────────────────

test.describe('Portal smoke — Sales', () => {
  test.use({ storageState: PORTAL_AUTH.sales });

  test('mounts without console errors', async ({ portalReady, consoleErrors }) => {
    await assertShellMounted(portalReady);
    void consoleErrors; // auto-asserted by fixture after test
  });

  test('shows Sales role label', async ({ portalReady }) => {
    await expect(portalReady.locator('.portal-user-role')).toContainText('Sales');
  });

  test('defaults to Customers section active', async ({ portalReady }) => {
    await expect(portalReady.locator('.portal-nav-item.active')).toContainText('Customers');
  });
});

// ── HR ────────────────────────────────────────────────────────────────────────

test.describe('Portal smoke — HR', () => {
  test.use({ storageState: PORTAL_AUTH.hr });

  test('mounts without console errors', async ({ portalReady, consoleErrors }) => {
    await assertShellMounted(portalReady);
    void consoleErrors;
  });

  test('shows HR Staff role label', async ({ portalReady }) => {
    await expect(portalReady.locator('.portal-user-role')).toContainText('HR Staff');
  });

  test('defaults to Customers section active', async ({ portalReady }) => {
    await expect(portalReady.locator('.portal-nav-item.active')).toContainText('Customers');
  });
});

// ── Manager ───────────────────────────────────────────────────────────────────

test.describe('Portal smoke — Manager', () => {
  test.use({ storageState: PORTAL_AUTH.manager });

  test('mounts without console errors', async ({ portalReady, consoleErrors }) => {
    await assertShellMounted(portalReady);
    void consoleErrors;
  });

  test('shows Manager role label', async ({ portalReady }) => {
    await expect(portalReady.locator('.portal-user-role')).toContainText('Manager');
  });

  test('defaults to Customers section active', async ({ portalReady }) => {
    await expect(portalReady.locator('.portal-nav-item.active')).toContainText('Customers');
  });
});

// ── Logged-out redirect ───────────────────────────────────────────────────────

test.describe('Portal access — unauthenticated', () => {
  // Use an empty auth state (no cookies) to simulate a logged-out visitor
  test.use({ storageState: { cookies: [], origins: [] } });

  test('redirects to WP login when not authenticated', async ({ page }) => {
    await page.goto('/portal');
    await expect(page).toHaveURL(/wp-login\.php/);
  });
});
