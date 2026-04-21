# Staff Portal — Gap Feature Plan
*Authored: 2026-04-21 | Sprint: Portal Completeness*

## Context

A gap analysis comparing the WP Admin PET submenus against the Portal's `PortalApp.tsx` routing
identified seven staff-facing features present in the admin but absent (or only partially present)
in the portal. This document captures the specification and implementation plan for all seven,
ordered by priority.

---

## Gaps and Priority

| # | Feature | Admin equivalent | Portal hash | Who sees it |
|---|---------|-----------------|-------------|-------------|
| 1 | My Profile | pet-my-profile | `#my-profile` | All portal users |
| 2 | Time History | pet-time | `#my-time` | All portal users (flag-gated) |
| 3 | My Performance (KPIs) | pet-performance | `#my-performance` | All portal users |
| 4 | My Projects (delivery view) | pet-delivery | `#projects` | All portal users (scoped by role) |
| 5 | Escalations | pet-escalations | `#escalations` | Manager / Admin |
| 6 | Advisory | pet-advisory | `#advisory` | Manager / Admin |
| 7 | Support Queue (team view) | pet-support | `#support` | Manager / Admin |

---

## Navigation Structure (post-gap-fill)

```
Commercial
  Customers · Catalog · Leads · Quotes · Approvals

People
  Employees

My Work  (all portal users)
  My Profile        ← NEW #1
  My Queue
  My Deliverables
  My Projects       ← NEW #4
  Calendar
  Log Time
  Time History      ← NEW #2
  My Performance    ← NEW #3
  Conversations
  Activity
  Knowledge Base

Management  (manager / admin only — NEW SECTION)
  Support Queue     ← NEW #7
  Escalations       ← NEW #5
  Advisory          ← NEW #6
```

---

## Feature Specifications

### 1 — My Profile (`#my-profile`)

**Purpose:** Staff can view (read-only) their own employee record — name, job title, hire date,
skills, team memberships.

**API:**
- `GET /pet/v1/staff/profile` ← **new endpoint** (returns current WP user's employee row + skills)
  - Permission: any logged-in portal user (`is_user_logged_in()`)
  - Uses `StaffEmployeeResolver` internally; fails gracefully if no employee record

**Data displayed:**
- Name, email, job title (from malleableData), hire date, status
- Skill ratings (name + proficiency level)
- Team memberships (names only)

**Page:** `src/UI/Portal/pages/MyProfilePage.tsx`

---

### 2 — Time History (`#my-time`)

**Purpose:** Staff can browse their full time-entry history, navigate by week, see daily and weekly
totals, and identify billable vs non-billable splits.

**API:**
- `GET /pet/v1/staff/time-capture/entries` (already exists — returns all entries for current user)
  - Client-side: group by week, navigate prev/next week, show daily rows + totals
  - The feature flag (`isStaffTimeCaptureEnabled`) gates this endpoint; page shows a
    "Time capture not enabled" notice if the flag is off.

**Data displayed:**
- Week picker (prev / next / current week)
- Per-day expandable rows showing time entries
- Daily sub-total + weekly total + billable/non-billable split

**Page:** `src/UI/Portal/pages/MyTimePage.tsx`

---

### 3 — My Performance (`#my-performance`)

**Purpose:** Staff can see their own KPI scores — current period targets vs actuals, score history,
and skill self-assessment overview.

**API:**
- `GET /pet/v1/staff/profile/kpis` ← **new endpoint** (returns KPIs for current user's employee)
  - Permission: any logged-in portal user
  - Internally: resolve WP user → employee ID → `PersonKpiRepository::findByEmployeeId()`

**Data displayed:**
- KPI cards: name, period, target, actual, score (colour-coded)
- Skills panel pulled from profile endpoint above
- Empty state if no KPIs recorded yet

**Page:** `src/UI/Portal/pages/MyPerformancePage.tsx`

---

### 4 — My Projects (`#projects`)

**Purpose:** Project-level view. For managers/admins: all projects with ticket counts and
completion stats. For regular staff: only projects where they have assigned tickets.

**API:**
- `GET /pet/v1/projects` (existing; returns all projects)
- `GET /pet/v1/tickets?lifecycle_owner=project` (existing; client-filter by `assignedUserId`)
- For manager/admin: full list, summary stats per project
- For staff: derive visible project IDs from own tickets

**Data displayed:**
- Project card: name, customer, state, ticket completion ratio (done/total)
- Expandable ticket list per project (WBS hierarchy)
- Status filter tabs: Active / All / Completed

**Page:** `src/UI/Portal/pages/ProjectsPage.tsx`

---

### 5 — Escalations (`#escalations`)

**Purpose:** Managers can see open escalations, acknowledge, and resolve them.

**API:**
- `GET /pet/v1/escalations` (existing; currently requires `manage_options`)
  - **Backend change:** open `checkPermission()` to also accept `pet_manager`
- `POST /pet/v1/escalations/{id}/acknowledge` (existing)
- `POST /pet/v1/escalations/{id}/resolve` (existing)

**Data displayed:**
- Escalation list: ticket subject, rule triggered, triggered at, status badge
- Acknowledge / Resolve inline buttons (optimistic UI)
- Feature flag `isEscalationEngineEnabled` gates the page entirely

**Page:** `src/UI/Portal/pages/EscalationsPage.tsx`

---

### 6 — Advisory (`#advisory`)

**Purpose:** Managers can select a customer and browse advisory reports and QBR packs.

**API:**
- `GET /pet/v1/customers` (existing; for customer selector)
- `GET /pet/v1/advisory/reports?customer_id={id}` (existing; currently requires `manage_options`)
  - **Backend change:** open read `checkPermission()` to `pet_manager`
- `GET /pet/v1/advisory/reports/latest?customer_id={id}` (existing)

**Data displayed:**
- Customer picker dropdown
- Report list: type, generated at, status
- Report detail: expandable text body

**Page:** `src/UI/Portal/pages/AdvisoryPage.tsx`

---

### 7 — Support Queue (`#support`)

**Purpose:** Team leads and managers can see the full helpdesk queue (all tickets with
`lifecycle_owner=helpdesk`), filter by status, and see ticket assignment.

**API:**
- `GET /pet/v1/tickets?lifecycle_owner=helpdesk` (existing; currently returns assigned-to-me
  subset for staff; with `manage_options` or `pet_manager` returns all)
  - **Backend check:** confirm TicketController scoping logic allows managers to see all

**Data displayed:**
- Ticket table: id, subject, customer, status badge, priority, assigned to, SLA status
- Status filter tabs: Open / All / My Team's
- Search by subject / customer

**Page:** `src/UI/Portal/pages/SupportQueuePage.tsx`

---

## Backend Changes Required

| File | Change |
|------|--------|
| `StaffTimeCaptureController.php` | Add `GET /staff/profile` and `GET /staff/profile/kpis` routes |
| `EscalationController.php` | Open `checkPermission()` to `pet_manager` (read + actions) |
| `AdvisoryReportController.php` | Open `checkPermission()` to `pet_manager` for read routes |
| `ApiRegistry.php` | Register `StaffProfileController` if extracted to separate class |
| `ContainerFactory.php` | DI wiring for `StaffProfileController` |

---

## Frontend Files Created / Modified

| File | Change |
|------|--------|
| `pages/MyProfilePage.tsx` | New — self-profile + skills view |
| `pages/MyTimePage.tsx` | New — weekly time history browser |
| `pages/MyPerformancePage.tsx` | New — KPI scores + skill summary |
| `pages/ProjectsPage.tsx` | New — project cards with ticket breakdown |
| `pages/EscalationsPage.tsx` | New — escalation list with ack/resolve |
| `pages/AdvisoryPage.tsx` | New — customer picker + report list |
| `pages/SupportQueuePage.tsx` | New — team helpdesk queue |
| `PortalApp.tsx` | Add 7 new routes + hash-prefix matches |
| `PortalShell.tsx` | Extend My Work nav + add Management nav section |

---

## Implementation Order

1. Backend: `StaffTimeCaptureController` — add `GET /staff/profile` and `GET /staff/profile/kpis`
2. Backend: `EscalationController` — open to `pet_manager`
3. Backend: `AdvisoryReportController` — open read to `pet_manager`
4. Frontend: `MyProfilePage`, `MyTimePage`, `MyPerformancePage`, `ProjectsPage`,
   `EscalationsPage`, `AdvisoryPage`, `SupportQueuePage`
5. Frontend: Wire into `PortalApp.tsx` and `PortalShell.tsx`
6. Build + deploy

---

## Acceptance Criteria

- [ ] `#my-profile` shows own employee record to any logged-in portal user
- [ ] `#my-time` shows weekly time history, navigable, with totals
- [ ] `#my-performance` shows KPI cards and skill panel for current user
- [ ] `#projects` shows scoped project list; staff see only projects with own tickets
- [ ] `#escalations` is visible to managers, hidden (Access Denied) to regular staff
- [ ] `#advisory` shows customer picker and reports for managers
- [ ] `#support` shows full helpdesk queue to managers, Access Denied to regular staff
- [ ] Management nav section only appears for users with `pet_manager` or `manage_options`
