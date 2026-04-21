/**
 * Portal-specific Playwright fixture.
 *
 * Extends the shared base fixture (consoleErrors) with:
 *  - Portal-specific benign console patterns
 *  - A `portalReady` fixture that navigates to /portal and waits for the
 *    React SPA shell to mount before handing control to the test.
 *
 * Usage:
 *   import { test, expect, PORTAL_AUTH } from './portal-base';
 *
 * Portal auth state files:
 *   PORTAL_AUTH.sales    → '.auth/portal-sales.json'
 *   PORTAL_AUTH.hr       → '.auth/portal-hr.json'
 *   PORTAL_AUTH.manager  → '.auth/portal-manager.json'
 *
 * Apply a role in a describe block:
 *   test.use({ storageState: PORTAL_AUTH.sales });
 */
import { test as base, expect } from '@playwright/test';
import type { Page } from '@playwright/test';

// ── Benign console patterns specific to the portal SPA ────────────────────────
const PORTAL_BENIGN_PATTERNS: RegExp[] = [
  /Download the React DevTools/,
  /Warning: Each child in a list should have a unique "key" prop/,
  /React does not recognize the .* prop/,
  // ui-avatars.com is an external CDN — network failures in test envs are benign
  /ui-avatars\.com/,
  // WP admin bar AJAX may fire on non-admin users
  /admin-ajax\.php/,
];

function isPortalBenign(text: string): boolean {
  return PORTAL_BENIGN_PATTERNS.some((p) => p.test(text));
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

interface PortalFixtures {
  /** Collected non-benign console errors — asserted empty after each test. */
  consoleErrors: string[];
  /** Navigates to /portal and resolves once the sidebar nav is mounted. */
  portalReady: Page;
}

export const test = base.extend<PortalFixtures>({
  consoleErrors: async ({ page }, use) => {
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' && !isPortalBenign(msg.text())) {
        errors.push(msg.text());
      }
    });

    page.on('pageerror', (err) => {
      if (!isPortalBenign(err.message)) {
        errors.push(err.message);
      }
    });

    await use(errors);

    expect(
      errors,
      `Unexpected browser console errors on portal:\n${errors.join('\n')}`
    ).toHaveLength(0);
  },

  portalReady: async ({ page }, use) => {
    await page.goto('/portal');
    // Wait for the PortalShell sidebar — this means the React app has mounted
    // and petSettings was read successfully.
    await page.waitForSelector('nav.portal-sidebar', { timeout: 15_000 });
    await use(page);
  },
});

export { expect };

// ── Auth state paths ──────────────────────────────────────────────────────────

export const PORTAL_AUTH = {
  sales:   '.auth/portal-sales.json',
  hr:      '.auth/portal-hr.json',
  manager: '.auth/portal-manager.json',
} as const;
