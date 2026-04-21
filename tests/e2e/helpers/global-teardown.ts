import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const PORTAL_SIDECAR_PATH = path.resolve('.auth/portal-test-users.json');
const ADMIN_AUTH_FILE     = path.resolve('.auth/admin.json');

const PORTAL_AUTH_FILES = [
  path.resolve('.auth/portal-sales.json'),
  path.resolve('.auth/portal-hr.json'),
  path.resolve('.auth/portal-manager.json'),
];

async function globalTeardown() {
  if (!fs.existsSync(PORTAL_SIDECAR_PATH)) {
    console.log('[playwright:global-teardown] No portal sidecar found — nothing to clean up.');
    return;
  }

  if (!fs.existsSync(ADMIN_AUTH_FILE)) {
    console.warn(
      '[playwright:global-teardown] No admin auth file — cannot authenticate to delete portal test users.'
    );
    return;
  }

  const baseURL = process.env.E2E_BASE_URL || 'https://pet4.cope.zone';

  const sidecar = JSON.parse(
    fs.readFileSync(PORTAL_SIDECAR_PATH, 'utf-8')
  ) as Record<string, { wpUserId: number; petEmployeeId: number | null }>;

  const browser = await chromium.launch();
  const adminCtx = await browser.newContext({
    ignoreHTTPSErrors: true,
    storageState: ADMIN_AUTH_FILE,
  });
  const adminPage = await adminCtx.newPage();

  // Navigate to portal to get nonce + apiUrl
  await adminPage.goto(`${baseURL}/portal`);
  await adminPage.waitForLoadState('networkidle');

  const { nonce, apiUrl } = await adminPage.evaluate(() => ({
    nonce:  (window as any).petSettings?.nonce  ?? '',
    apiUrl: (window as any).petSettings?.apiUrl ?? '',
  }));

  if (!nonce) {
    console.warn('[playwright:global-teardown] Could not get nonce — skipping API cleanup.');
    await browser.close();
    return;
  }

  for (const [role, userData] of Object.entries(sidecar)) {
    const { wpUserId, petEmployeeId } = userData;

    // 1. Archive the PET employee record (soft-delete via the REST endpoint)
    if (petEmployeeId && apiUrl) {
      await adminPage.evaluate(
        async ({ apiUrl, nonce, petEmployeeId }) => {
          try {
            await fetch(`${apiUrl}/employees/${petEmployeeId}`, {
              method: 'DELETE',
              headers: { 'X-WP-Nonce': nonce },
            });
          } catch {
            // best-effort
          }
        },
        { apiUrl, nonce, petEmployeeId }
      );
    }

    // 2. Hard-delete the WP user, reassigning their content to user ID 1 (admin)
    const deleteResult = await adminPage.evaluate(
      async ({ baseURL, wpUserId, nonce }) => {
        try {
          const res = await fetch(
            `${baseURL}/wp-json/wp/v2/users/${wpUserId}?force=true&reassign=1`,
            { method: 'DELETE', headers: { 'X-WP-Nonce': nonce } }
          );
          return { ok: res.ok, status: res.status };
        } catch (err: any) {
          return { ok: false, status: -1, error: err.message };
        }
      },
      { baseURL, wpUserId, nonce }
    );

    if (deleteResult.ok) {
      console.log(
        `[playwright:global-teardown] Deleted portal ${role} user (wpUserId=${wpUserId})`
      );
    } else {
      console.warn(
        `[playwright:global-teardown] Could not delete portal ${role} user ` +
          `(wpUserId=${wpUserId}, status=${deleteResult.status}). ` +
          'The user may need to be removed manually.'
      );
    }
  }

  await browser.close();

  // ── Clean up auth files ────────────────────────────────────────────────────
  fs.unlinkSync(PORTAL_SIDECAR_PATH);
  console.log('[playwright:global-teardown] Removed portal sidecar.');

  for (const f of PORTAL_AUTH_FILES) {
    if (fs.existsSync(f)) {
      fs.unlinkSync(f);
      console.log(`[playwright:global-teardown] Removed ${path.basename(f)}`);
    }
  }

  console.log('[playwright:global-teardown] Portal test user cleanup complete.');
}

export default globalTeardown;
