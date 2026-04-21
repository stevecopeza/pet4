# Staff Portal — UX Improvement Proposal
Version: v1.1
Status: **PROPOSAL — awaiting approval before implementation**
Date: 2026-04-20
Updated: 2026-04-20 — Section 4 (mobile) updated with Steve's input on time logging, submission, and ticket actions.
Author: AI Agent / Steve Cope

---

## 1. Context and Honest Assessment

The portal built in the v1.0 sprint is functionally complete and architecturally sound. The role gating, API wiring, component structure, and quote lifecycle all work correctly. However, the **layout pattern** was applied uniformly from a reference design without asking whether it was optimal for each page. It was not.

### The current pattern
Every page uses: **2/3 list (left) + 1/3 sliding detail panel (right)**.

This pattern originates from email clients (Gmail, Outlook) where the **list is the primary navigation** — you're constantly hopping between messages and the list stays relevant. That is not what staff are doing in this portal.

When a manager opens a customer record to view contacts, or an HR user opens an employee to check their certifications, **the list is irrelevant**. It becomes visual noise behind a panel that is genuinely too narrow for rich content.

### What the research found
- The admin panel already has rich employee sub-components: `EmployeeSkills.tsx`, `EmployeeRoles.tsx`, `EmployeeCertifications.tsx`, `EmployeeReviews.tsx`. The portal does not use any of them.
- Shortcodes (`pet_my_work`, `pet_my_approvals`, `pet_my_calendar`) are **server-rendered HTML** with a working `@media (max-width: 600px)` breakpoint in `shortcodes.css`. They are mostly read-only. Some mobile-friendly infrastructure already exists.
- The portal CSS uses `position: fixed` viewport anchoring which makes responsive design for the SPA non-trivial.
- There is exactly **one** CSS mobile breakpoint in `portal.css` — the admin bar offset at 782px. Nothing else.

---

## 2. UX Pattern Decision

### The change: full-page detail routes

For all complex entities, clicking a row should navigate to a **full-page detail view** (route change, browser back button works) rather than opening a side panel.

| Entity | Current | Proposed |
|---|---|---|
| Customers | 2/3 list + 1/3 panel | List → full-page (tabs: Details / Contacts / Quotes / Leads) |
| Employees | 2/3 list + 1/3 panel | List → full-page (tabs: Identity / Organisation / Roles / Skills / Certs / Reviews) |
| Leads | 2/3 list + 1/3 panel | List → full-page (tabs: Details / Linked Quotes) |
| Quotes | 2/3 list + 1/3 panel | List → full-page (tabs: Details / Blocks / Approval History) |
| Approvals | Card list | **No change** — cards with inline approve/reject is correct here |
| Catalog | Tab list | **No change** — items are compact; modal for create/edit is correct |

### Where modals stay correct
Modals are right for **focused, short-horizon tasks**:
- Creating a new customer, lead, employee, quote
- Quick status changes (archive, reject with note)
- Confirmation dialogs

Modals are wrong for:
- Viewing a record with sub-data
- Editing a record with multiple sections
- Anything with tabs or linked lists

### URL model
Hash router extended to support entity detail routes:

| Route | Renders |
|---|---|
| `#customers` | Customer list |
| `#customers/42` | Customer full-page detail |
| `#employees` | Employee list |
| `#employees/7` | Employee full-page detail |
| `#leads` | Leads list |
| `#leads/12` | Lead full-page detail |
| `#quotes` | Quotes list |
| `#quotes/5` | Quote full-page detail |
| `#quote-builder-5` | Quote builder (already full-screen, unchanged) |

The back button on detail pages returns to the list, preserving scroll position (stored in component state or URL fragment).

---

## 3. Employee Page — Blueprint Implementation

The Employee page is the richest case and serves as the template for all others. Completing it correctly establishes every pattern needed downstream.

### 3a. List view (unchanged structure, improved detail)

```
[KPI strip: Total | Active | No portal access | Archived]

[Provision New Employee]

┌─────────────────────────────────────────────────────────┐
│  Avatar  Name                Role      Status  Team      │  ← click row → #employees/7
│  🟢       Sarah Chen          Sales     Active  Delivery  │
│  🔵       Marcus Webb         HR        Active  People    │
│  ⚫       Tom Walsh           —         Active  —         │
└─────────────────────────────────────────────────────────┘
```

Provision New Employee opens a **modal** (short focused form: name, email, portal role).

### 3b. Detail page (`#employees/7`)

```
← Back to Employees

┌─── Sarah Chen ─────────────────────────────────────── [Edit] [Archive] ───┐
│  🟢 Active · Sales · Delivery Team · Reports to: Marcus Webb              │
└───────────────────────────────────────────────────────────────────────────┘

[Identity] [Organisation] [Roles] [Skills] [Certifications] [Reviews]
                                                             ← active tab
┌──────────────────────────────────────────────────────────────────────────┐
│  Tab content renders here — full width, no cramping                      │
└──────────────────────────────────────────────────────────────────────────┘
```

#### Tab: Identity
- First name, last name, email, phone, title, hire date, status
- Portal role (manager only; HR can see but not change)
- Edit inline — PUT `/employees/:id`

#### Tab: Organisation
- Current teams (pills with join date)
- Manager name (link to manager's detail page)
- Add/remove team membership
- Reuses data already on the Employee record (`teamIds`)

#### Tab: Roles
- Role assignments table: Role name / Active from / Active to / Rate snapshot
- Add new role assignment
- Deactivate existing assignment
- Port of admin `EmployeeRoles.tsx` — same REST endpoints

#### Tab: Skills
- Skills table: Skill / Self-rating / Manager rating / Last reviewed
- Add skill / edit ratings
- Port of admin `EmployeeSkills.tsx` — same REST endpoints

#### Tab: Certifications
- Cert cards: Cert name / Expiry / Status badge (Active / Expiring Soon / Expired)
- Add certification / mark expired
- Port of admin `EmployeeCertifications.tsx` — same REST endpoints

#### Tab: Reviews
- Review cycle history
- Port of admin `EmployeeReviews.tsx` — same REST endpoints

### 3c. Capability gating on tabs

| Tab | pet_hr | pet_manager | pet_sales |
|---|---|---|---|
| Identity | read/write | read/write | — (not in nav) |
| Organisation | read | read/write | — |
| Roles | read | read/write | — |
| Skills | read/write | read/write | — |
| Certifications | read/write | read/write | — |
| Reviews | read | read/write | — |

Sales users do not see the Employees section at all (no `pet_hr` or `pet_manager` cap).

### 3d. This pattern becomes the template
Every other entity detail page (Customers, Leads, Quotes) follows the same structure:
1. Full-page layout with header strip (entity name, status, key context)
2. Tab bar
3. Full-width tab content
4. Back chevron to list

---

## 4. Mobile Strategy

### The governing principle

Mobile is not a scaled-down version of the portal. It is a different set of **fast, focused actions for people who are moving** — on-site, in transit, or winding down at the end of the day. The test for whether something belongs on mobile is: *"Would someone do this in two minutes on their phone, possibly just before they go to bed?"*

Not everything passes that test. Build only what does.

### Architecture: separate `/my` pages, not a responsive portal

The portal (`/portal`) stays desktop-first. The `position: fixed` layout, sidebar, and rich canvas components are designed for 1024px+ screens and should not be compromised.

Mobile users get a separate set of lightweight WP pages using shortcodes, under a `/my` parent:

```
pet.cope.zone/my              → landing page (nav links to all /my sections)
pet.cope.zone/my/time         → [pet_log_time]  (new — top priority)
pet.cope.zone/my/approvals    → [pet_my_approvals] (extended with actions)
pet.cope.zone/my/today        → [pet_my_work]  (exists, mostly works)
pet.cope.zone/my/tickets      → [pet_my_tickets] (new)
```

These pages use `shortcodes.css` (already has a `@media (max-width: 600px)` breakpoint), are simple server-rendered HTML, and load fast on a phone — no React bootstrap, no SPA overhead. Scoped to the logged-in user automatically.

The portal header detects viewport < 768px and shows a banner: *"For mobile use, visit [My Work →]"* — honest and helpful rather than pretending the portal works on a phone.

### Do NOT attempt to make the portal SPA responsive
The portal uses `position: fixed` for all three regions. Making it fully responsive would require a separate layout, separate navigation, and different interaction patterns — at which point it is a different application. The `/my` shortcode pages are that application.

---

### Mobile use cases — detailed design

#### Priority 1: Time logging — `[pet_log_time]`

**The problem:** Field staff cannot log time until they return to a desk. By then they've forgotten what they did. Schedules and capacity cannot be managed without knowing what was completed today. Currently, time entry requires the admin panel.

**Design constraints (confirmed):**
- Input is **duration** (e.g. "45 min") and **date** (default today, changeable for yesterday)
- NOT start/end times — too precise, too friction-heavy for field use
- The ticket picker must be **curated and intelligent**, not a raw list of every open ticket
- Submission is **immediate** — straight to `submitted` state, not draft

**Intelligent ticket curation — how the picker works:**

The form shows a short ranked list under "Likely working on:" — max 8 items:
1. Tickets currently assigned to this user with `status = in_progress` (project lifecycle)
2. Active support tickets assigned to this user
3. Tickets this user has logged time against in the last 7 days
4. Work items assigned to this user with high priority score

Below the curated list: a collapsible "Other assigned tickets" section, then a search field (by customer name or ticket reference) as a last resort. For most sessions, the correct ticket is in the top 3.

**Form design — target: 3 taps + one number:**

```
┌─ Log Time ──────────────────────────────────────────┐
│                                                      │
│  🗓  Today  ▾  (tap to change — yesterday etc.)      │
│                                                      │
│  Likely working on:                                  │
│  ● Acme Corp — Firewall Config #42   ← tap to select │
│  ○ RPM — VPN Setup #38                               │
│  ○ Westfield — Laptop Rollout #51                    │
│  + Other assigned / Search                           │
│                                                      │
│  ⏱  [  45  ] minutes                                │
│                                                      │
│  💰 Billable  ●  /  ○ Not billable                  │
│                                                      │
│  📝 Note (optional) ▾                                │
│                                                      │
│         [ Log Time ]                                 │
└──────────────────────────────────────────────────────┘
```

On submit: `POST /pet/v1/time-entries` with `status: submitted`. A `start` time is computed as `date 09:00` and `end` as `date 09:00 + duration` — the exact times are irrelevant for billing and scheduling; only the duration and date matter on mobile.

Success: *"✓ 45 min logged on Acme Firewall #42"* with a 10-second undo.

**On the compliance problem ("getting them to do it"):**

The hardest part is habit formation. Frictionless UX is necessary but not sufficient. The `/my/time` page shows:
- *"Today: 2h 15min logged"* — simple daily accumulator
- *"5 days in a row ✓"* — streak indicator (lightweight `pet_time_streaks` table or `pet_settings` JSON)
- If nothing logged by 5pm: a gentle *"Nothing logged today yet"* nudge on the `/my` landing

A **team leaderboard** (time logged this week per person) is a feature-flag option (`pet_timesheet_leaderboard_enabled`, off by default). It can be motivating or toxic depending on team culture — leave the decision to the manager.

**Key business unlock:** a technician logging 45 minutes at 6pm means a manager knows by 7pm that the work is done. Scheduling and capacity decisions can't be made without this signal.

---

#### Priority 2: Approvals with actions — extend `[pet_my_approvals]`

Current shortcode is read-only. Extension adds Approve / Reject inline.

**Behaviour:**
- Each approval card gets two buttons: **Approve ✓** and **Reject ✗**
- Approve: single confirmation tap → `POST /pet/v1/quotes/:id/approve` → card fades out
- Reject: tap → rejection note textarea slides in → Submit → card fades out
- Implemented as a small `fetch()` JS block alongside the existing shortcode HTML — no React needed

**This is the classic "before bed" action.** Manager gets a ping (eventually — push/email, future), opens phone, sees 2 pending quotes, taps Approve on both. Done.

---

#### Priority 3: My Today — `[pet_my_work]`

Already exists and has a 600px mobile breakpoint. Minor improvements only:
- SLA urgency badges larger and more prominent on small screens
- Each item row: tapping "Log time" deep-links to `/my/time?ticket=ID` with that ticket pre-selected in the form

---

#### Priority 4: Support tickets on the move — `[pet_my_tickets]`

**The scenario:** A technician is on-site or has just responded to a junior colleague. They need to record what happened and route the work correctly — from their phone.

**Three actions, nothing more:**

1. **Log time** — same form as `[pet_log_time]`, pre-loaded with this ticket. `PATCH` on submit.
2. **Resolve** — single tap + optional note → `PATCH /pet/v1/tickets/:id/status` → `resolved`
3. **Reassign** — dropdown of team members + optional note → `POST /pet/v1/tickets/:id/assign-to-employee`
   *(Also covers the "route back to a junior" scenario — reassign to them with a note)*

What is NOT on mobile: full ticket editing, ticket creation, team/queue assignment, SLA management.

---

#### Priority 5: Quote adjustments — deferred

Even a "minor" price adjustment requires knowing the quote structure. This is deferred pending traction on priorities 1–4.

---

### What is NOT going on mobile

| Capability | Decision | Reasoning |
|---|---|---|
| Build a quote | ❌ Never | Canvas, mouse, sustained attention |
| Employee management | ❌ Never | HR desk work |
| Catalog management | ❌ Never | Admin desk work |
| Full advisory/reports | ❌ Never | Briefing room, not field |
| Customer full edit | ❌ Never | Desk task |
| Customer view (read-only) | ⚠️ v2 | Could be useful; low priority |
| Quote price adjustment | ⚠️ Deferred | More thought needed |

---

## 5. Tests: portal E2E

### What exists
- Playwright + Vitest already configured (`npm run test:e2e`)
- Admin tests in `tests/e2e/admin/` with a clean fixture/helper pattern
- Auth stored in `.auth/admin.json` — works for `manage_options` users
- No portal tests exist

### Auth fixtures (prerequisite for all portal tests)

Three auth JSON files: `.auth/portal-sales.json`, `.auth/portal-hr.json`, `.auth/portal-manager.json`

Created in `tests/e2e/portal/global-setup.ts`:
1. Login as admin
2. Call `POST /pet/v1/employees/provision` to create three test employees with portal roles
3. Login as each portal user, save `storageState`
4. Clean up in `global-teardown.ts`

These users are created fresh per test run and torn down after — no dependency on seeded data.

### What needs building

#### Test files to create

| File | Coverage |
|---|---|
| `tests/e2e/portal/smoke.spec.ts` | All portal routes load for each role; no console errors; correct nav items visible |
| `tests/e2e/portal/customers.spec.ts` | List loads; create via modal; detail page opens; edit; contacts sub-tab |
| `tests/e2e/portal/employees.spec.ts` | List loads; provision modal; detail page + tabs; skills/certs visible |
| `tests/e2e/portal/leads.spec.ts` | List loads; create; status filter; convert to quote |
| `tests/e2e/portal/quotes.spec.ts` | List loads; create; open builder; submit for approval |
| `tests/e2e/portal/approvals.spec.ts` | Manager sees queue; approve; reject with note; quote removed from queue |
| `tests/e2e/portal/role-gates.spec.ts` | Sales cannot reach Employees or Approvals; HR cannot reach Leads/Quotes |
| `tests/e2e/portal/journey-quote-lifecycle.spec.ts` | End-to-end: create lead → convert → build quote → submit → approve |

#### Test pattern (mirroring existing admin tests)
```typescript
// tests/e2e/portal/smoke.spec.ts
import { test, expect } from '../fixtures/portal-base';  // new fixture

test.describe('Portal Smoke — Sales role', () => {
  test.use({ storageState: '.auth/portal-sales.json' });

  test('portal mounts at /portal', async ({ page }) => {
    await page.goto('/portal');
    await page.waitForFunction(() =>
      document.getElementById('pet-portal-root')?.children.length > 0,
      { timeout: 15_000 }
    );
    await expect(page.locator('#pet-portal-root')).toBeAttached();
  });

  test('sales nav shows Customers, Catalog, Leads, Quotes — not Employees or Approvals', async ({ page }) => {
    await page.goto('/portal');
    // ... wait for mount
    await expect(page.getByRole('link', { name: 'Customers' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Employees' })).toBeHidden();
    await expect(page.getByRole('link', { name: 'Approvals' })).toBeHidden();
  });
});
```

---

## 6. Implementation Plan

All phases follow the rule: **document → plan → build → test → update docs**.

### Phase 0: Auth fixtures + portal smoke *(prerequisite — enables all other test phases)*
Estimated: 0.5 days

- `tests/e2e/portal/global-setup.ts` — provision 3 portal users (sales/hr/manager) via `POST /employees/provision`, save `storageState`
- `tests/e2e/portal/global-teardown.ts` — clean up test users
- `tests/e2e/portal/fixtures/portal-base.ts` — Playwright fixture wrapping portal auth
- `tests/e2e/portal/smoke.spec.ts` — all routes × all three roles
- `tests/e2e/portal/role-gates.spec.ts` — nav items visible/hidden per capability

### Phase 1: Employee page — blueprint *(establishes full-page detail pattern)*
Estimated: 2 days

1. Extend hash router: `#employees` (list) + `#employees/:id` (detail)
2. `EmployeeListView` — existing list, each row links to `#employees/:id`; Provision New opens modal
3. `EmployeeDetailView` — full-page with header strip + tab bar:
   - `EmployeeIdentityTab` — name, email, status, portal role (manager-gated), hire date
   - `EmployeeOrganisationTab` — teams (pills), manager (link to their detail page)
   - `EmployeeRolesTab` — port of admin `EmployeeRoles.tsx`, same REST endpoints
   - `EmployeeSkillsTab` — port of admin `EmployeeSkills.tsx`, same REST endpoints
   - `EmployeeCertificationsTab` — port of admin `EmployeeCertifications.tsx`
   - `EmployeeReviewsTab` — port of admin `EmployeeReviews.tsx`
4. Remove slide panel from `EmployeesPage.tsx` entirely
5. `tests/e2e/portal/employees.spec.ts`
6. Update `docs/42_staff_portal/` with employee page spec

### Phase 2: Apply pattern to Customers, Leads, Quotes
Estimated: 2 days

Same pattern, same structure:
- `CustomersPage.tsx` → tabs: Details / Contacts / Active Quotes
- `LeadsPage.tsx` → tabs: Details / Linked Quotes
- `QuotesPage.tsx` → tabs: Details / Blocks (read-only) / Approval History

Write `customers.spec.ts`, `leads.spec.ts`, `quotes.spec.ts`. Update docs.

### Phase 3: Mobile — time logging *(highest-value mobile feature)*
Estimated: 1.5 days

- New `[pet_log_time]` shortcode in `ShortcodeRegistrar.php`
- Smart ticket picker: curated from assigned + recent + active work items
- Form: date (default today, changeable) + ticket + duration (minutes) + billable toggle + optional note
- Submit → `POST /pet/v1/time-entries` with `status: submitted`; start/end computed from date + duration
- Streak indicator (daily habit reinforcement)
- Create WP pages: `/my` (parent nav) + `/my/time`
- Update `PET_Implemented_Shortcodes_Reference_v2_0.md`

### Phase 4: Mobile — approvals with actions
Estimated: 0.5 days

- Extend `[pet_my_approvals]`: Approve/Reject buttons via `fetch()`
- Reject requires note; Approve is single-tap confirm
- Create WP page `/my/approvals`
- Update shortcode reference doc

### Phase 5: Mobile — support tickets
Estimated: 1 day

- New `[pet_my_tickets]` shortcode — open tickets assigned to user
- Per-ticket actions: Log Time (deep-link to `/my/time?ticket=ID`), Resolve, Reassign + note
- Create WP page `/my/tickets`
- Update shortcode reference doc

### Phase 6: Portal mobile banner + journey E2E test
Estimated: 0.5 days

- Portal header: viewport < 768px → show `/my` link banner
- `tests/e2e/portal/journey-quote-lifecycle.spec.ts` — full end-to-end lifecycle
- Final doc pass across all changed files

---

## 7. What is NOT changing

- `QuoteBuilderPage.tsx` — full-screen, already correct
- `ApprovalsPage.tsx` — card layout with inline approve/reject is right
- `CatalogPage.tsx` — table + modal create/edit is correct
- All REST endpoints, permission gating, capability model
- Admin panel — untouched throughout

---

## 8. Decisions recorded (Steve's answers, 2026-04-20)

All questions from the original proposal are now answered. No open questions before Phase 0.

| Question | Answer |
|---|---|
| `/my` URL structure | Subpages under `/my` parent ✓ |
| Time input on mobile | Duration (minutes) + date. NOT start/end times. |
| Ticket picker | Curated/intelligent: assigned + recent + active. Search as fallback. |
| Submission state on mobile | Submitted immediately — skip draft |
| Compliance / habit strategy | Frictionless UX + streak indicator. Leaderboard optional, feature-flagged, off by default. |
| Support ticket mobile actions | Log time (billable) + resolve + reassign/route to colleague with note |
| Quote adjustments on mobile | Deferred — pending traction on priorities 1–4 |

---

## Appendix: Mobile decision matrix (final)

| Capability | Mobile? | Where | Reasoning |
|---|---|---|---|
| Log time | ✅ Priority 1 | `/my/time` | Field staff, daily habit, scheduling depends on it |
| Approve/reject quotes | ✅ Priority 2 | `/my/approvals` | Classic before-bed manager action |
| My today / task list | ✅ Exists | `/my/today` | Already mostly works; minor polish |
| Support ticket: log + resolve + route | ✅ Priority 4 | `/my/tickets` | Technician on-site or in transit |
| Build a quote | ❌ Never | — | Canvas, mouse, sustained attention |
| Employee management | ❌ Never | — | HR desk work |
| Catalog management | ❌ Never | — | Admin desk work |
| Full advisory/reports | ❌ Never | — | Briefing room, not field |
| Customer view (read-only) | ⚠️ v2 | — | Useful for techs; low priority |
| Quote price adjustment | ⚠️ Deferred | — | Needs more thought; deferred pending traction |
