import { test as base, expect } from '@playwright/test';

/**
 * Allowlisted console messages that are not treated as errors.
 * Add patterns here for known benign messages.
 */
const BENIGN_PATTERNS = [
  /Download the React DevTools/,
  /React does not recognize the .* prop/,
  /Warning: Each child in a list should have a unique "key" prop/,
  /favicon\.ico/,
];
const SERVER_RENDERED_PET_PAGE_HINTS = [
  'page=pet-shortcodes',
  'page=pet-demo-tools',
];

const SERVER_RENDERED_BENIGN_PATTERNS = [
  /api-js\.mixpanel\.com/,
  /Failed to load resource: net::ERR_FAILED/,
  /pointer is not a function/,
  /Invalid or unexpected token/,
];

function isServerRenderedPetPage(url: string): boolean {
  return SERVER_RENDERED_PET_PAGE_HINTS.some((hint) => url.includes(hint));
}

function isBenign(text: string, pageUrl: string): boolean {
  const scopedPatterns = isServerRenderedPetPage(pageUrl)
    ? [...BENIGN_PATTERNS, ...SERVER_RENDERED_BENIGN_PATTERNS]
    : BENIGN_PATTERNS;
  return scopedPatterns.some((pattern) => pattern.test(text));
}

export const test = base.extend<{ consoleErrors: string[] }>({
  consoleErrors: async ({ page }, use) => {
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' && !isBenign(msg.text(), page.url())) {
        errors.push(msg.text());
      }
    });

    page.on('pageerror', (err) => {
      if (!isBenign(err.message, page.url())) {
        errors.push(err.message);
      }
    });

    await use(errors);

    // After the test, fail if any unexpected console errors occurred
    expect(
      errors,
      `Unexpected browser console errors:\n${errors.join('\n')}`
    ).toHaveLength(0);
  },
});

export { expect };
