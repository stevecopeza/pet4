import { chromium, FullConfig } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { execSync } from 'child_process';

const authFile = path.resolve('.auth/admin.json');
const manifestFile = path.resolve('dist/.vite/manifest.json');

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
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  // Navigate to WP login
  await page.goto(`${baseURL}/wp-login.php`);

  // Fill login form
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();

  // Wait for redirect to wp-admin
  await page.waitForURL('**/wp-admin/**');

  // Save authenticated state
  await context.storageState({ path: authFile });

  await browser.close();
}

export default globalSetup;
