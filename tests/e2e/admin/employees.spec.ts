import { test, expect } from '../fixtures/base';
import { getNonce, getApiUrl } from '../helpers/api';

/**
 * Employees CRUD tests.
 *
 * NOTE: Creating an employee via the UI requires selecting an available WP user
 * from a dropdown. On environments where all WP users already have employee
 * records, that dropdown is empty and the UI cannot create a new employee.
 *
 * To keep the test self-contained and independent of the seeded data state,
 * the employee is created directly via the REST API in `beforeAll`. The
 * edit and archive flows are then exercised through the UI as normal.
 */
test.describe('Admin > Employees CRUD', () => {
  // Shared state: null means no test employee was created (skip data-dependent tests)
  let testEmployeeId: number | null = null;
  const firstName = 'E2ETest';
  let lastName = `Emp${Date.now()}`;
  let updatedLastName = lastName + 'Upd';

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-people');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);

      if (!nonce || !apiUrl) return;

      // Find a WP user not yet linked to an employee record
      const usersRes = await page.request.get(`${apiUrl}/employees/available-users`, {
        headers: { 'X-WP-Nonce': nonce },
      });

      if (!usersRes.ok()) return;

      const users = await usersRes.json();
      if (!Array.isArray(users) || users.length === 0) return; // Nothing available — tests will be skipped

      const wpUserId = Number(users[0].ID);

      const createRes = await page.request.post(`${apiUrl}/employees`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
          wpUserId,
          firstName,
          lastName,
          email: `e2e-employee-${Date.now()}@example.com`,
          status: 'active',
        },
      });

      if (createRes.ok()) {
        const created = await createRes.json();
        testEmployeeId = created.id ?? null;
      }
    } catch {
      // best-effort — tests will skip if no employee was created
    } finally {
      await context.close();
    }
  });

  test.beforeEach(async ({ page }) => {
    if (testEmployeeId === null) return; // avoid navigation when data is unavailable

    await page.goto('/wp-admin/admin.php?page=pet-people');
    await page.waitForFunction(() => {
      const el = document.getElementById('pet-admin-root');
      return el !== null && el.children.length > 0;
    }, { timeout: 15_000 });

    // Ensure we are on the People tab (it is the default, but be explicit)
    await page.getByRole('button', { name: 'People', exact: true }).click();
    // Wait for the employee table to render
    await expect(page.locator('table').first()).toBeVisible({ timeout: 10_000 });
  });

  test('People tab loads with an employee table', async ({ page, consoleErrors }) => {
    test.skip(testEmployeeId === null, 'No test employee available — skipping data-dependent test');
    await expect(page.locator('table').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Add New Employee' })).toBeVisible();
  });

  test('can edit an employee via the form', async ({ page, consoleErrors }) => {
    test.skip(testEmployeeId === null, 'No test employee available — skipping data-dependent test');

    // Click the test employee's name button in the table
    await page.getByRole('button', { name: new RegExp(`${firstName}.*${lastName}`) }).click();
    await expect(page.getByRole('heading', { name: /Edit Employee/i })).toBeVisible();

    // Update the last name
    const lastNameInput = page.getByLabel('Last Name:');
    await lastNameInput.clear();
    await lastNameInput.fill(updatedLastName);

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/employees/') && resp.request().method() === 'PUT'
    );
    await page.getByRole('button', { name: 'Update Employee' }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(200);

    // Updated name should appear in the table
    await expect(
      page.getByRole('button', { name: new RegExp(`${firstName}.*${updatedLastName}`) })
    ).toBeVisible({ timeout: 10_000 });
  });

  test('can archive an employee via kebab menu', async ({ page, consoleErrors }) => {
    test.skip(testEmployeeId === null, 'No test employee available — skipping data-dependent test');
    page.on('dialog', (dialog) => dialog.accept());

    const row = page.locator('tr', { hasText: updatedLastName });
    await expect(row).toBeVisible();

    await row.locator('.pet-kebab-menu, [class*="kebab"]').first().click();
    await page.getByText('Archive', { exact: true }).click();

    await page.waitForResponse(
      (resp) => resp.url().includes('/pet/v1/employees/') && resp.request().method() === 'DELETE'
    );

    await expect(
      page.getByRole('button', { name: new RegExp(`${firstName}.*${updatedLastName}`) })
    ).toBeHidden({ timeout: 5_000 });

    testEmployeeId = null; // Already archived — prevent afterAll from trying again
  });

  test.afterAll(async ({ browser }) => {
    if (testEmployeeId === null) return;

    const context = await browser.newContext({
      storageState: '.auth/admin.json',
      ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    try {
      await page.goto('/wp-admin/admin.php?page=pet-people');
      await page.waitForFunction(() => {
        const el = document.getElementById('pet-admin-root');
        return el !== null && el.children.length > 0;
      }, { timeout: 15_000 });

      const nonce = await getNonce(page);
      const apiUrl = await getApiUrl(page);
      if (!nonce || !apiUrl) return;

      await page.request.delete(`${apiUrl}/employees/${testEmployeeId}`, {
        headers: { 'X-WP-Nonce': nonce },
      });
    } catch {
      // best-effort cleanup
    } finally {
      await context.close();
    }
  });
});
