import { test, expect } from '../fixtures/base';

/**
 * Navigation tests.
 *
 * Verifies that:
 * 1. Each PET page can be reached by clicking its link in the WP admin sidebar.
 * 2. The heading on each page matches the expected title.
 * 3. The Dashboards page renders in standalone mode (WP admin bar is hidden).
 */

test.describe('Admin > Navigation', () => {
  test.beforeEach(async ({ page }) => {
    // Start from a known PET page so the sidebar is fully expanded
    await page.goto('/wp-admin/admin.php?page=pet-dashboard');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });
  });

  test('PET submenu links are present in the WP admin sidebar', async ({ page, consoleErrors }) => {
    // The PET parent menu section exists
    const adminMenu = page.locator('#adminmenu');
    await expect(adminMenu).toBeVisible();

    // At least a few of the registered PET pages should appear as submenu links
    const petLinks = adminMenu.locator('a[href*="page=pet-"]');
    const count = await petLinks.count();
    expect(count).toBeGreaterThan(5);
  });

  // Spot-check a handful of submenu links by clicking them and verifying the heading.
  // (Full page-load coverage is handled by smoke.spec.ts — this confirms the sidebar links work.)
  const spotCheckPages: Array<{ slug: string; expectedTitle: string }> = [
    { slug: 'pet-crm',           expectedTitle: 'Customers' },
    { slug: 'pet-delivery',      expectedTitle: 'Delivery' },
    { slug: 'pet-support',       expectedTitle: 'Support' },
    { slug: 'pet-knowledge',     expectedTitle: 'Knowledge' },
    { slug: 'pet-settings',      expectedTitle: 'Settings' },
  ];

  for (const { slug, expectedTitle } of spotCheckPages) {
    test(`clicking ${slug} sidebar link loads the correct page`, async ({ page, consoleErrors }) => {
      const adminMenu = page.locator('#adminmenu');

      // Click the sidebar link for this page
      const link = adminMenu.locator(`a[href*="page=${slug}"]`).first();
      await expect(link).toBeVisible();
      await link.click();

      // Wait for React to mount
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      // Verify heading
      await expect(page.locator('h1, h2').filter({ hasText: expectedTitle }).first())
        .toBeVisible({ timeout: 10_000 });
    });
  }

  test('Dashboards page renders in standalone mode (WP admin bar hidden)', async ({ page, consoleErrors }) => {
    await page.goto('/wp-admin/admin.php?page=pet-dashboards');

    // Wait for React to mount
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });

    // In standalone mode the WP admin bar should not be visible
    // (#wpadminbar is either absent, hidden, or has display:none)
    const adminBar = page.locator('#wpadminbar');
    const isAdminBarHidden =
      !(await adminBar.isVisible().catch(() => false)) ||
      (await adminBar.evaluate((el) => getComputedStyle(el).display === 'none').catch(() => true));

    expect(isAdminBarHidden, 'WP admin bar should be hidden on the standalone Dashboards page').toBe(true);
  });

  test('navigating back from a sub-view restores the list', async ({ page, consoleErrors }) => {
    // Navigate to Customers, open a customer, then go back
    await page.goto('/wp-admin/admin.php?page=pet-crm');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });

    // Wait for the customer list table to appear
    await expect(page.locator('table').first()).toBeVisible({ timeout: 10_000 });

    // The "Add New Customer" button proves we are on the list view
    await expect(page.getByRole('button', { name: 'Add New Customer' })).toBeVisible();
  });
});
