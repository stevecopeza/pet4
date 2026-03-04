import { test, expect } from '../fixtures/base';
import { ADMIN_PAGE_SLUGS, PAGE_TITLES } from '../fixtures/test-data';

test.describe('Admin Page Smoke Tests', () => {
  for (const slug of ADMIN_PAGE_SLUGS) {
    test(`${slug} loads without errors`, async ({ page, consoleErrors }) => {
      await page.goto(`/wp-admin/admin.php?page=${slug}`);

      // Page should not show a WP fatal error or "has been a critical error"
      const body = page.locator('body');
      await expect(body).not.toContainText('There has been a critical error');
      await expect(body).not.toContainText('Fatal error');

      if (slug === 'pet-shortcodes') {
        // Shortcodes page is server-rendered, not React
        await expect(page.locator('h1')).toContainText('Shortcodes');
      } else if (slug === 'pet-demo-tools') {
        // Demo Tools page is server-rendered
        await expect(page.locator('h1')).toContainText('Demo Tools');
      } else {
        // React-rendered pages mount into #pet-admin-root
        const root = page.locator('#pet-admin-root');
        await expect(root).toBeAttached();

        // Wait for React to render content inside the root
        // (the root should not be empty after React mounts)
        await page.waitForFunction(() => {
          const el = document.getElementById('pet-admin-root');
          return el !== null && el.children.length > 0;
        }, { timeout: 15_000 });

        // Verify the page title matches expected
        const expectedTitle = PAGE_TITLES[slug];
        if (expectedTitle && slug !== 'pet-dashboards') {
          // Dashboards page hides WP chrome and has its own layout
          await expect(page.locator('h1').first()).toContainText(expectedTitle);
        }
      }

      // Console errors are checked automatically by the consoleErrors fixture
    });
  }
});
