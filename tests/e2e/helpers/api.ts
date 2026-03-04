import { Page } from '@playwright/test';

/**
 * Extracts the WP REST nonce from the current page's petSettings global.
 * The page must be a PET admin page (the nonce is injected via wp_localize_script).
 */
export async function getNonce(page: Page): Promise<string> {
  return page.evaluate(() => (window as any).petSettings?.nonce ?? '');
}

/**
 * Returns the PET REST API base URL from the current page's petSettings global.
 */
export async function getApiUrl(page: Page): Promise<string> {
  return page.evaluate(() => (window as any).petSettings?.apiUrl ?? '');
}
