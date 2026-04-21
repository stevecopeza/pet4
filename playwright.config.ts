import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'path';

dotenv.config();

const baseURL = process.env.E2E_BASE_URL || 'https://pet4.cope.zone';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['github'], ['html']] : [['html']],
  timeout: 30_000,

  globalSetup: './tests/e2e/helpers/global-setup.ts',
  globalTeardown: './tests/e2e/helpers/global-teardown.ts',

  use: {
    baseURL,
    storageState: '.auth/admin.json',
    trace: process.env.CI ? 'on-first-retry' : 'off',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: true,
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
