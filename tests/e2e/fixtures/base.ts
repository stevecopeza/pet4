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

function isBenign(text: string): boolean {
  return BENIGN_PATTERNS.some((pattern) => pattern.test(text));
}

export const test = base.extend<{ consoleErrors: string[] }>({
  consoleErrors: async ({ page }, use) => {
    const errors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' && !isBenign(msg.text())) {
        errors.push(msg.text());
      }
    });

    page.on('pageerror', (err) => {
      errors.push(err.message);
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
