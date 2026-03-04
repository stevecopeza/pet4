STATUS: IMPLEMENTED
SCOPE: End-to-End Testing Strategy
VERSION: v1.1

# E2E Testing Strategy (v1)

## Purpose

Defines how PET validates that its user-facing surfaces work correctly from the perspective of a real user interacting with a browser. E2E tests exist to catch integration failures that unit and integration tests cannot: broken page loads, API wiring failures, form submission regressions, navigation errors, and rendering bugs in the React admin UI and PHP shortcode pages.

The goal is high confidence that a deployed change does not break the user's experience.

## Relationship to Existing Test Layers

PET's testing philosophy (doc 01) defines three boundaries:

- **Domain tests** — pure logic, no DB, no WP (PHPUnit, `tests/Unit/`)
- **Infrastructure tests** — database round-trips, migrations (PHPUnit, `tests/Integration/`)
- **UI tests** — never contain business logic

E2E tests are the implementation of that third layer. They verify that:

1. Pages load without error
2. Data returned by the REST API renders correctly
3. User actions (click, type, submit) produce the expected visible result
4. Navigation between PET admin pages works
5. Shortcode pages render for logged-in users

E2E tests do **not** test domain invariants or business rules. Those belong in the unit test suite. If an E2E test needs to assert a business outcome (e.g. "quote transitions to Accepted"), the assertion is on the **visible UI state**, not on the domain model.

## Tool Choice: Playwright

### Why Playwright

- First-class TypeScript support — matches PET's frontend stack (React + TS + Vite)
- Built-in headless Chromium, Firefox, WebKit — no external browser install required for CI
- Native `request` context for API-level tests (used by the demo contract tests in doc 24)
- Auto-waiting and locator-based assertions eliminate flaky `sleep()`-style waits
- Trace viewer and screenshot-on-failure for debugging without a display
- Already referenced in `composer.json` (`test:e2e`) and in the demo contract tests doc (`docs/24_demo_system/contract_tests.md`)

### What Playwright replaces

No prior E2E framework existed. Puppeteer, Cypress, and Selenium were evaluated and rejected:

- **Puppeteer** — Chromium only, no built-in assertion library, lower-level API
- **Cypress** — opinionated runner that does not support multi-tab or multi-origin well (WP admin login is a separate origin from shortcode front-end pages)
- **Selenium** — heavyweight setup, slower execution, Java/Python bias

## Test Scope

### What E2E tests cover

#### 1. Admin Page Smoke Tests
Every PET admin page registered in `AdminPageRegistry.php` must load without a JavaScript console error or a PHP fatal. This is the minimum bar — if a page crashes, nothing else matters.

Pages to cover (derived from `AdminPageRegistry::addMenuPage()`):

- `pet-dashboard` (Overview)
- `pet-dashboards` (Dashboards — standalone, hides WP chrome)
- `pet-crm` (Customers)
- `pet-quotes-sales` (Quotes & Sales)
- `pet-finance` (Finance)
- `pet-delivery` (Delivery)
- `pet-time` (Time)
- `pet-support` (Support)
- `pet-conversations` (Conversations)
- `pet-approvals` (Approvals)
- `pet-knowledge` (Knowledge)
- `pet-people` (Staff)
- `pet-roles` (Roles & Capabilities)
- `pet-activity` (Activity)
- `pet-settings` (Settings)
- `pet-pulseway` (Pulseway RMM)
- `pet-shortcodes` (Shortcodes)
- `pet-demo-tools` (Demo Tools)

#### 2. CRUD Workflow Tests
For each core entity that supports list → create → edit → archive, test the full cycle through the UI:

- **Customers** — add customer via form, verify it appears in the DataTable, edit it, archive it, verify it disappears from the active list
- **Employees** — same pattern
- **Quotes** — create draft quote, add components, verify totals render, archive
- **Projects** — create project, verify it appears in Delivery list
- **Support Tickets** — create ticket, verify it renders in Support, verify SLA badge appears
- **Time Entries** — create time entry, submit, verify immutability (edit button disabled or absent)
- **Knowledge Articles** — create, edit, archive

These tests interact with the real REST API (`/pet/v1/*`) through the browser — they do not mock fetch calls.

#### 3. Navigation and Routing
- Clicking each WP admin submenu item loads the correct PET page (the `<h1>` reads the expected title)
- The Dashboards page renders in standalone mode (no WP admin bar visible)
- React ErrorBoundary catches and renders a fallback for broken components (simulated by injecting a bad prop)

#### 4. Shortcode Page Tests
For each registered shortcode, verify it renders correctly on a front-end WordPress page:

- `[pet_my_profile]` — renders the profile card for a logged-in user
- `[pet_my_work]` — renders the work items panel
- `[pet_my_calendar]` — renders the calendar timeline
- `[pet_activity_stream]` — renders activity groups (Today, Yesterday, etc.)
- `[pet_helpdesk]` — renders KPI strip and ticket lanes
- `[pet_my_conversations]` — renders conversation list
- `[pet_my_approvals]` — renders pending approvals
- `[pet_knowledge_base]` — renders article list

Each shortcode test verifies:
- The root element (`.pet-my-profile`, `.pet-my-work`, etc.) is present
- No PHP fatal or JS console error occurred
- The "please log in" message appears for unauthenticated visits

#### 5. Demo System Tests
As specified in `docs/24_demo_system/contract_tests.md`:

- `POST /pet/v1/system/demo/seed_full` returns 201 with `seed_run_id`
- Seeded entities are visible in the admin UI (spot-check: at least one customer, one quote, one ticket appear)
- `POST /pet/v1/system/demo/purge` returns 200
- After purge, seeded entities are no longer visible

#### 6. State Guard Tests
Key invariants that should be visible in the UI:

- A submitted time entry cannot be edited (the Edit button is absent or disabled)
- An accepted quote cannot be edited (form fields are locked)
- Archiving an entity triggers a confirmation dialog before the action completes
- Bulk archive selects multiple rows, confirms, and removes them from the visible list

### What E2E tests do NOT cover

- Domain logic validation (covered by PHPUnit unit tests)
- Database schema correctness (covered by integration tests)
- External integrations (QuickBooks, Pulseway) — these are mocked or stubbed at the API boundary
- Performance / load testing (separate concern, see docs/11)
- Visual regression / pixel-diff testing (not in scope for v1; may be added later)

## Directory Structure

```
tests/
├── bootstrap.php              (existing — PHPUnit)
├── Unit/                      (existing — PHPUnit)
├── Integration/               (existing — PHPUnit, future)
└── e2e/
    ├── fixtures/
    │   ├── base.ts            (custom test fixture: consoleErrors collection)
    │   └── test-data.ts       (shared constants: testLabel(), page slugs, titles)
    ├── helpers/
    │   ├── global-setup.ts    (WP login → saves storageState to .auth/admin.json)
    │   └── api.ts             (getNonce(), getApiUrl() — read from petSettings)
    └── admin/
        ├── smoke.spec.ts      (page-load smoke for all 18 admin pages)
        ├── customers.spec.ts  (CRUD workflow — serial)
        ├── employees.spec.ts  (CRUD workflow — serial)
        ├── quotes.spec.ts     (CRUD workflow — serial)
        ├── projects.spec.ts   (CRUD workflow — serial)
        ├── support.spec.ts    (CRUD workflow — serial)
        ├── time-entries.spec.ts (CRUD workflow — serial)
        ├── knowledge.spec.ts  (CRUD workflow — serial)
        └── navigation.spec.ts (submenu routing, standalone dashboards)
```

Future directories (not yet implemented):
- `shortcodes/` — shortcode rendering tests
- `demo/` — demo seed/purge contract tests
- `guards/` — state guard tests (immutability, lock, bulk archive)

## Configuration

### `playwright.config.ts` (plugin root)

Key settings:

- **baseURL**: Read from `E2E_BASE_URL` env var (default: `https://pet4.cope.zone`)
- **globalSetup**: `tests/e2e/helpers/global-setup.ts` — logs in via `wp-login.php` and saves cookies to `.auth/admin.json`
- **storageState**: `.auth/admin.json` — persistent authenticated session, avoids logging in before every test
- **fullyParallel**: `true` — test files run in parallel across workers. Within a file, `test.describe.serial()` enforces ordering for CRUD suites.
- **projects**: Chromium only for speed in local dev; add Firefox and WebKit in CI
- **retries**: 0 locally, 2 in CI
- **timeout**: 30 seconds per test (override to 60s via `--timeout 60000` for slow environments)
- **use.trace**: `on-first-retry` in CI for post-mortem debugging
- **use.screenshot**: `only-on-failure`
- **use.ignoreHTTPSErrors**: `true` (self-signed certs on dev environments)
- **reporter**: `html` locally, `github` + `html` in CI
- **dotenv**: Config loads `.env` automatically via `dotenv` package

### Environment Variables

All environment-specific values are provided via `.env` or shell environment — never hardcoded:

- `E2E_BASE_URL` — WordPress site URL (default: `http://pet4.local`)
- `E2E_WP_USERNAME` — WP admin username for login
- `E2E_WP_PASSWORD` — WP admin password for login
- `E2E_SHORTCODE_PAGE_URL` — URL of a WP page containing all test shortcodes (see Test Data Setup below)

A `.env.example` file will be provided. `.env` is gitignored.

## Authentication

WordPress requires a logged-in session (`manage_options` capability) to access admin pages and the PET REST API.

### Approach: Persistent storageState

1. A **global setup** script (`tests/e2e/helpers/global-setup.ts`) runs once before all tests
2. It navigates to `wp-login.php`, fills in credentials from `E2E_WP_USERNAME` / `E2E_WP_PASSWORD`
3. It saves the browser's cookies and localStorage to `.auth/admin.json`
4. All test projects reference this `storageState`, so every test starts already logged in
5. The `.auth/` directory is gitignored

This avoids repeating the login flow in every test file and eliminates a common source of flakiness.

### API Authentication

For tests that call the REST API directly (e.g. demo seed/purge), the `api.ts` helper:

1. Reads the `petSettings.nonce` from the DOM of any loaded admin page
2. Passes it as `X-WP-Nonce` header on all `request.fetch()` calls

This mirrors how the React frontend authenticates — no separate API key mechanism is needed.

## Conventions

### Naming
- Files: `{feature}.spec.ts` inside the appropriate subdirectory
- Test suites: `test.describe('Admin > Customers', ...)` — mirrors the PET menu hierarchy
- Individual tests: `test('can create a customer and see it in the list', ...)`

### Locators
- Prefer `page.getByRole()`, `page.getByLabel()`, `page.getByText()` over CSS selectors
- When CSS is unavoidable (e.g. PET-specific classes), use `page.locator('.pet-admin-dashboard')` and document why
- Never use XPath
- Never use `data-testid` attributes unless they already exist — PET does not currently use them, and adding them requires a separate decision

### Assertions
- Use Playwright's built-in `expect(locator).toBeVisible()`, `toHaveText()`, `toHaveCount()` — these auto-wait
- Assert the absence of console errors on every page load (via a shared `page.on('console')` listener that collects errors)
- Never `expect(true).toBe(true)` — every assertion must test something observable

### Waiting
- Prefer Playwright's auto-waiting locators over explicit waits
- If an operation triggers an API call, wait for the network response: `page.waitForResponse(resp => resp.url().includes('/pet/v1/customers'))`
- `page.waitForFunction()` is acceptable for waiting on DOM conditions (e.g. select options loading)
- `page.waitForLoadState('networkidle')` is acceptable after actions that trigger cascading API calls (e.g. selecting a customer loads sites)
- `page.waitForTimeout()` should be avoided but is acceptable as a last resort for async React state settling

### Test Isolation
- Each test **file** is independent — no ordering dependencies between files
- Within a CRUD file, tests use `test.describe.serial()` to enforce create → read → archive ordering (these tests share state via `const` variables)
- CRUD tests clean up in `afterAll` — the cleanup block fetches entities matching `E2E Test *` via the REST API and deletes them
- Tests must not assume specific database state beyond what they set up themselves
- The demo seed/purge mechanism (`/pet/v1/system/demo/seed_full` and `/purge`) can be used to establish a known baseline for tests that need realistic data volume

### Console Error Collection
Every test automatically fails if the browser console contains `error`-level messages during the test run. Implementation:

- A shared `beforeEach` hook attaches a `page.on('console', ...)` listener
- Errors are collected into an array
- An `afterEach` hook asserts the array is empty
- Known benign errors (e.g. React DevTools messages in development) are filtered out via an allowlist

## Test Data Setup

### Shortcode Test Page
Shortcode tests require a WordPress page that embeds all PET shortcodes. This page is created once during environment setup (not by the tests themselves):

- Page title: `PET E2E Test Shortcodes`
- Page content: one instance of each shortcode (`[pet_my_profile]`, `[pet_my_work]`, etc.)
- The page URL is provided via `E2E_SHORTCODE_PAGE_URL`

### CRUD Test Data
CRUD tests create their own data via the UI and clean up after themselves. Test data uses a recognisable naming convention so it can be identified and cleaned up if a test fails mid-run:

- Customer names: `E2E Test Customer - {timestamp}`
- Employee names: `E2E Test Employee - {timestamp}`
- Quote names: `E2E Test Quote - {timestamp}`

A cleanup utility (`tests/e2e/helpers/cleanup.ts`) can be run manually to remove orphaned test data matching the `E2E Test *` pattern.

## Running Tests

### Prerequisites

1. Copy `.env.example` to `.env` and fill in credentials
2. Install Playwright browsers: `npx playwright install --with-deps chromium`
3. Ensure the React bundle is built: `npm run build`

### Local Development

```bash
# Run all E2E tests (reads .env automatically)
npx playwright test

# Run a specific test file
npx playwright test tests/e2e/admin/customers.spec.ts

# Run with extended timeout for slow environments
npx playwright test --timeout 60000

# Run in headed mode (visible browser) for debugging
npx playwright test --headed tests/e2e/admin/customers.spec.ts

# Run with Playwright UI mode (interactive)
npx playwright test --ui

# View the HTML report after a run
npx playwright show-report
```

### Without `.env` file

```bash
E2E_BASE_URL=https://pet4.cope.zone E2E_WP_USERNAME=admin E2E_WP_PASSWORD=pw npx playwright test
```

### CI

CI runs are configured to:

1. Start or connect to a WordPress instance with the PET plugin activated
2. Run `npx playwright install --with-deps` to ensure browsers are available
3. Run `npx playwright test` with all three browser projects (Chromium, Firefox, WebKit)
4. Upload the HTML report and any failure traces as CI artifacts
5. Fail the build if any test fails after retries

CI configuration is environment-specific and is not defined in this document.

## Failure Triage

When an E2E test fails:

1. **Check the HTML report** — `npx playwright show-report` shows screenshots, traces, and console logs for every failure
2. **Check the trace** — in CI, download the trace artifact and open it with `npx playwright show-trace trace.zip` for a step-by-step replay
3. **Check for console errors** — the collected console errors often point directly to the root cause (e.g. a 500 from the REST API)
4. **Check test isolation** — if the test passes locally but fails in CI, check whether it depends on database state left by another test

### Known Flakiness Mitigations

- **WP admin page load time**: The 30-second timeout accommodates cold-start WP page loads. If this is consistently too short, investigate WP object caching.
- **REST API race conditions**: CRUD tests wait for the API response before asserting on the UI, not the other way around.
- **Session expiry**: The `storageState` approach re-authenticates in global setup before each full run. Individual tests do not re-authenticate.

## Implementation Status

### Phase 1: Foundation ✅
- Playwright config, global-setup authentication, console error collection fixture
- Admin smoke tests for all 18 pages (load and check for errors)
- Customers CRUD workflow

### Phase 2: Core CRUD ✅
- CRUD workflows: Customers, Employees, Quotes, Projects, Support, Time Entries, Knowledge (all serial)
- Navigation and routing tests (submenu routing, standalone dashboards)
- 46 total tests (43 pass, 3 skipped)

### Phase 3: Shortcodes and Guards (future)
- Shortcode rendering tests
- State guard tests (immutability, lock, bulk archive)

### Phase 4: Demo System (future)
- Demo seed/purge contract tests
- Spot-check that seeded entities appear in the UI

## Success Criteria

The E2E test suite is considered reliable when:

- All admin pages load without console errors in all three browsers
- CRUD tests for all core entities pass consistently (zero flaky failures over 10 consecutive CI runs)
- Shortcode pages render correctly for authenticated users
- State guards (immutability, lock) are enforced in the UI
- A developer can run the full suite locally in under 5 minutes
- Any team member can read this document, set up the environment, and run the tests without additional guidance

## Known Patterns and Workarounds

### Form Label Accessibility
All PET form components use `htmlFor`/`id` pairs with the convention `id="pet-{form}-{field}"` (e.g. `pet-ticket-customer`, `pet-customer-name`). This enables Playwright's `getByLabel()` locator.

### Long Forms and `requestSubmit()`
Forms that extend beyond the viewport (e.g. TicketForm) may not submit reliably via Playwright's button `click()` due to viewport scrolling. Use `form.requestSubmit()` via `page.evaluate()` instead — this is the standard DOM API for programmatic form submission with native validation.

### React Controlled Select Elements
Use `selectOption({ index: N })` or `selectOption(value)` to set `<select>` values. Verify the selection took effect with `await expect(locator).not.toHaveValue('')` — React controlled components may not always reflect the change immediately.

### Server-Rendered Pages
Pages like `pet-shortcodes` and `pet-demo-tools` are server-rendered (not React). `AdminPageRegistry.php` returns early from `enqueueScripts()` for these pages to prevent the React bundle from loading and logging a "root not found" console error.

## Dependencies

- Node.js >= 18 (already required by the Vite build)
- `@playwright/test` and `dotenv` in `devDependencies`
- A running WordPress instance with the PET plugin activated and built (`npm run build`)
- A WordPress admin user with `manage_options` capability
- `.env` file with `E2E_BASE_URL`, `E2E_WP_USERNAME`, `E2E_WP_PASSWORD`

---

**Authority**: Normative

This document defines PET's E2E testing strategy and conventions.
