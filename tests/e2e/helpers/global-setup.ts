import { chromium, FullConfig } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { execSync } from 'child_process';

const authFile = path.resolve('.auth/admin.json');
const manifestFile = path.resolve('dist/.vite/manifest.json');

// ── Portal test user definitions ──────────────────────────────────────────────
//
// These users are provisioned fresh each test run and deleted in global-teardown.
// Emails use a `.internal` domain that will never exist in production data.
//
const PORTAL_TEST_PASSWORD = 'E2ePortal!2026';

interface PortalTestUser {
  key: string;
  firstName: string;
  lastName: string;
  email: string;
  portalRole: string;
  authFile: string;
}

const PORTAL_TEST_USERS: PortalTestUser[] = [
  {
    key: 'sales',
    firstName: 'E2E',
    lastName: 'Sales',
    email: 'e2e-test-sales@pet.internal',
    portalRole: 'pet_sales',
    authFile: '.auth/portal-sales.json',
  },
  {
    key: 'hr',
    firstName: 'E2E',
    lastName: 'Hr',
    email: 'e2e-test-hr@pet.internal',
    portalRole: 'pet_hr',
    authFile: '.auth/portal-hr.json',
  },
  {
    key: 'manager',
    firstName: 'E2E',
    lastName: 'Manager',
    email: 'e2e-test-manager@pet.internal',
    portalRole: 'pet_manager',
    authFile: '.auth/portal-manager.json',
  },
];

/** Path to the sidecar JSON used by global-teardown to identify which users to delete. */
export const PORTAL_SIDECAR_PATH = path.resolve('.auth/portal-test-users.json');

// ── Build check ───────────────────────────────────────────────────────────────

function ensureFrontendBuildArtifacts(): void {
  const hasManifest = fs.existsSync(manifestFile);
  if (hasManifest) {
    return;
  }

  console.log('[playwright:global-setup] Frontend build artifacts missing. Running npm run build...');
  execSync('npm run build', { stdio: 'inherit' });

  if (!fs.existsSync(manifestFile)) {
    throw new Error(
      'Missing frontend build artifact: dist/.vite/manifest.json. ' +
        'Playwright requires built PET admin assets. Build failed or produced no manifest.'
    );
  }
}

// ── Main ──────────────────────────────────────────────────────────────────────

async function globalSetup(config: FullConfig) {
  ensureFrontendBuildArtifacts();

  const baseURL = config.projects[0].use.baseURL!;
  const username = process.env.E2E_WP_USERNAME;
  const password = process.env.E2E_WP_PASSWORD;

  if (!username || !password) {
    throw new Error(
      'E2E_WP_USERNAME and E2E_WP_PASSWORD must be set. ' +
        'Copy .env.example to .env and fill in your credentials.'
    );
  }

  // Ensure .auth directory exists
  const authDir = path.dirname(authFile);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  const browser = await chromium.launch();

  // ── 1. Admin login ─────────────────────────────────────────────────────────
  const adminContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const adminPage = await adminContext.newPage();

  await adminPage.goto(`${baseURL}/wp-login.php`);
  await adminPage.locator('#user_login').fill(username);
  await adminPage.locator('#user_pass').fill(password);
  await adminPage.locator('#wp-submit').click();
  await adminPage.waitForURL('**/wp-admin/**');

  await adminContext.storageState({ path: authFile });
  console.log('[playwright:global-setup] Admin auth saved.');

  // ── 2. Navigate to portal to extract petSettings (nonce + apiUrl) ──────────
  await adminPage.goto(`${baseURL}/portal`);
  await adminPage.waitForLoadState('networkidle');

  const { nonce, apiUrl } = await adminPage.evaluate(() => ({
    nonce:  (window as any).petSettings?.nonce  ?? '',
    apiUrl: (window as any).petSettings?.apiUrl ?? '',
  }));

  if (!nonce || !apiUrl) {
    console.warn(
      '[playwright:global-setup] WARNING: Could not read petSettings from /portal. ' +
        'Portal tests require a WordPress page with the [pet_portal] shortcode at /portal. ' +
        'Skipping portal user provisioning — portal tests will not run.'
    );
    await browser.close();
    return;
  }

  // ── 3. Provision each portal test user ────────────────────────────────────
  const sidecar: Record<string, { wpUserId: number; petEmployeeId: number | null }> = {};

  for (const user of PORTAL_TEST_USERS) {
    // 3a. Best-effort cleanup: delete any WP user left over from a failed previous run
    await adminPage.evaluate(
      async ({ baseURL, email, nonce }) => {
        try {
          const res = await fetch(
            `${baseURL}/wp-json/wp/v2/users?search=${encodeURIComponent(email)}&context=edit`,
            { headers: { 'X-WP-Nonce': nonce } }
          );
          if (!res.ok) return;
          const users = (await res.json()) as any[];
          for (const u of users) {
            await fetch(`${baseURL}/wp-json/wp/v2/users/${u.id}?force=true&reassign=1`, {
              method: 'DELETE',
              headers: { 'X-WP-Nonce': nonce },
            });
          }
        } catch {
          // Best-effort — do not propagate
        }
      },
      { baseURL, email: user.email, nonce }
    );

    // 3b. Provision: creates WP user + employee record + grants portal cap atomically
    const provision = await adminPage.evaluate(
      async ({ apiUrl, nonce, userData }) => {
        const res = await fetch(`${apiUrl}/employees/provision`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({
            firstName: userData.firstName,
            lastName: userData.lastName,
            email: userData.email,
            portalRole: userData.portalRole,
            status: 'active',
            hireDate: '2026-01-01',
          }),
        });
        const data = await res.json();
        return { ok: res.ok, status: res.status, data };
      },
      { apiUrl, nonce, userData: user }
    );

    if (!provision.ok) {
      await browser.close();
      throw new Error(
        `[playwright:global-setup] Provision failed for ${user.key} user: ` +
          JSON.stringify(provision.data)
      );
    }

    const wpUserId = provision.data.wpUserId as number;

    // 3c. Look up the PET employee record ID (needed for teardown archival)
    const empList = await adminPage.evaluate(
      async ({ apiUrl, nonce }) => {
        const res = await fetch(`${apiUrl}/employees`, { headers: { 'X-WP-Nonce': nonce } });
        if (!res.ok) return [];
        return res.json() as Promise<any[]>;
      },
      { apiUrl, nonce }
    );
    const petEmployee = (empList as any[]).find((e: any) => e.wpUserId === wpUserId);
    const petEmployeeId: number | null = petEmployee?.id ?? null;

    sidecar[user.key] = { wpUserId, petEmployeeId };

    // 3d. Set a known, deterministic password so tests can log in predictably
    const pwResult = await adminPage.evaluate(
      async ({ baseURL, wpUserId, password, nonce }) => {
        const res = await fetch(`${baseURL}/wp-json/wp/v2/users/${wpUserId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          body: JSON.stringify({ password }),
        });
        return { ok: res.ok, status: res.status };
      },
      { baseURL, wpUserId, password: PORTAL_TEST_PASSWORD, nonce }
    );

    if (!pwResult.ok) {
      await browser.close();
      throw new Error(
        `[playwright:global-setup] Failed to set password for ${user.key} ` +
          `(wpUserId=${wpUserId}, status=${pwResult.status}). ` +
          'Check that the admin user has manage_options capability.'
      );
    }

    // 3e. Log in as this portal user and save their storageState
    const portalCtx = await browser.newContext({ ignoreHTTPSErrors: true });
    const portalPage = await portalCtx.newPage();

    await portalPage.goto(`${baseURL}/wp-login.php`);
    await portalPage.locator('#user_login').fill(user.email);
    await portalPage.locator('#user_pass').fill(PORTAL_TEST_PASSWORD);
    await portalPage.locator('#wp-submit').click();
    // Non-admin users redirect to wp-admin (profile) or dashboard — wait for any post-login page
    await portalPage.waitForLoadState('networkidle');

    await portalCtx.storageState({ path: user.authFile });
    await portalCtx.close();

    console.log(
      `[playwright:global-setup] Portal ${user.key} user ready ` +
        `(wpUserId=${wpUserId}, petEmployeeId=${petEmployeeId})`
    );
  }

  // ── 4. Persist sidecar for global-teardown ────────────────────────────────
  fs.writeFileSync(PORTAL_SIDECAR_PATH, JSON.stringify(sidecar, null, 2));
  console.log('[playwright:global-setup] Portal test sidecar written:', sidecar);

  await browser.close();
}

export default globalSetup;
