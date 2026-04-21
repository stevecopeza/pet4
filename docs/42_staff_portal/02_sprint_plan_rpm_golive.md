# RPM Go-Live Sprint Plan — Staff Portal
Version: v1.1
Status: **IMPLEMENTED — all 10 days delivered 2026-04-20**
Date: 2026-04-20
Target go-live: 2026-04-30 (10 working days)

---

## Objective

Deliver a polished, front-end staff portal at `pet.cope.zone/portal` that enables RPM's ~8 staff members to perform the full commercial workflow — customers, catalog, employees, leads, and quotes including approval — without touching the WordPress admin panel.

Design target: match the existing admin dashboard aesthetic (`pet-dashboards` page). System UI fonts, `#2563eb` accent, card-based layout, clean tables.

---

## Scope Summary

### In Scope

| Area | Detail |
|---|---|
| Portal shell | Sidebar nav, header, role-gated routing |
| WP capabilities | 3 new caps: `pet_sales`, `pet_hr`, `pet_manager` |
| Customers | Full CRUD, contacts sub-view |
| Catalog | Items (services) + products; HR/manager write, sales read |
| Employees | Full CRUD + simultaneous WP user account creation |
| Leads | Create, list, view; link to customer |
| Quote list | Status view, filter by state |
| Quote builder | All block types: service, hardware, text, price adjustment, project |
| Approval queue | Manager-only; approve/reject with notes |
| PDF generation | Download only; generic professional A4 template |
| Permission layer | REST endpoints opened to portal capabilities alongside existing `manage_options` |

### Out of Scope (v1.0)

- Customer-facing quote acceptance (staff accepts on customer's behalf for now)
- Automated email sending (user downloads PDF, sends from their email client)
- Mobile layout optimisation (tablet-friendly acceptable)
- Helpdesk, tickets, time tracking, projects from portal
- Multi-tenant data isolation

---

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Portal URL | `pet.cope.zone/portal` | Clean, staff-facing |
| Architecture | Single `[pet_portal]` shortcode → React SPA | Reuses all existing React components; one bundle to maintain |
| Build | Vite multi-entry (admin + portal) | Shared component tree-shaking; no code duplication |
| Roles | WP capabilities (not WP roles) | Per-user granularity; no disruption to existing admin users |
| WP user creation | Server-side on employee save | Atomic; HR form just passes intent (name, email, portal role) |
| PDF | HTML-for-print endpoint; browser print-to-PDF | No TCPDF dependency needed; professional output via browser native print |
| Quote builder | Portal-native block editor | Admin block components are WP-admin-CSS-coupled; purpose-built portal editor cleaner |
| Admin panel | Unchanged, remains fully operational | Portal is additive; no existing functionality disturbed |

---

## Risk Register

| Risk | Severity | Mitigation |
|---|---|---|
| Quote builder scope (most complex component) | HIGH | Day 8 targets 4 common block types. Project blocks Day 9. Builder falls back to admin panel if Day 9 slips — list + approval already operational by Day 7. |
| Permission callback breadth (~12 controllers) | MEDIUM | `PortalPermissionHelper` is one class; controller updates are mechanical once pattern is established. Budget 3 hours. |
| PDF layout quality | MEDIUM | Ship functional layout Day 10. Visual polish is v1.1. |
| Employee WP user creation edge cases (duplicate email, username collision) | MEDIUM | Try/catch; employee record saves regardless; UI shows dismissible warning. Never block the core save. |
| Vite dual-entry build issues | LOW | Multi-entry is a standard Rollup pattern; manifest-based WP asset loading handles it cleanly. |

### MVP Fallback (if quote builder slips)

The critical path for RPM going live is: **login → customers → employees → catalog → leads → quote list**. If Days 8–9 (quote builder) slip, quote creation falls back to the admin panel temporarily. Days 1–7 are the non-negotiable floor.

---

## Day-by-Day Plan

### Day 1 — Permissions, Capabilities & Build Foundation

**Goal:** Portal infrastructure in place; staff can log in and see a blank portal.

Tasks:
- Register `pet_sales`, `pet_hr`, `pet_manager` WP capabilities on plugin activation
- Create `src/UI/Rest/Support/PortalPermissionHelper.php` with `check(string ...$caps): bool`
- Add `checkPortalPermission()` to the 12 affected REST controllers (see Architecture doc)
- Add second Vite entry in `vite.config.ts`: `portal: 'src/UI/Portal/main.tsx'`
- Scaffold `src/UI/Portal/main.tsx` — mounts `<PortalApp />` (empty for now)
- Scaffold `src/UI/Portal/Shortcode/PortalShortcode.php` — enqueues portal bundle, injects `window.petSettings`, renders `<div id="pet-portal-root">`
- Register `[pet_portal]` shortcode in `ShortcodeRegistrar.php`
- Create WordPress page with slug `/portal`, body `[pet_portal]`

Done when: Logged-in staff user navigates to `/portal` and sees a React app mount (even if just "Portal loading…").

---

### Day 2 — Portal Shell (Sidebar, Routing, Role Detection)

**Goal:** Navigation works; role-gated sections visible/hidden correctly.

Tasks:
- `src/UI/Portal/PortalApp.tsx` — router setup (React Router or hash-based), wraps `<PortalShell>`
- `src/UI/Portal/PortalShell.tsx` — fixed sidebar (240px), header bar (56px), main content area
- `src/UI/Portal/hooks/usePortalUser.ts` — reads `window.petSettings.currentUser.capabilities` array
- `src/UI/Portal/components/ProtectedRoute.tsx` — capability guard; redirects to "Access denied" if not held
- `portal.css` — layout CSS using `--sc-accent` and design tokens from `shortcodes.css`
- Sidebar nav items rendered conditionally per capability:
  - Customers — `pet_sales | pet_hr | pet_manager`
  - Catalog — `pet_sales | pet_hr | pet_manager`
  - Employees — `pet_hr | pet_manager`
  - Leads — `pet_sales | pet_manager`
  - Quotes — `pet_sales | pet_manager`
  - Approvals — `pet_manager` only

Done when: Three test users (one per capability) log in and see the correct subset of nav items.

---

### Day 3 — Customers Module

**Goal:** Sales staff can list, create, edit, and view customers and their contacts.

Tasks:
- Import `Customers.tsx` + `CustomerForm.tsx` into portal Customers route
- Wrap in `PortalSection` container (portal-specific page wrapper, no WP admin chrome)
- Verify all API calls work with portal nonce (`window.petSettings` injection confirmed)
- Add Contacts sub-view within customer detail (CustomerController already has `/pet/v1/customers/{id}/contacts`)
- Strip any admin-only navigation links that reference WP admin URLs
- Empty state: "No customers yet — add your first one" with create button

Done when: Sales staff can CRUD customers from the portal.

---

### Day 4 — Catalog Module (Services + Products)

**Goal:** HR/managers can manage the rate card; sales staff can browse it read-only.

Tasks:
- Import `Catalog.tsx` (service items) + `CatalogProducts.tsx` into portal Catalog route
- Render in two tabs: **Services** | **Products**
- Apply read-only mode for `pet_sales`: hide create/edit/delete buttons, render table only
- Apply full CRUD for `pet_hr` + `pet_manager`
- `CatalogItemController` and `CatalogProductController` — `checkPortalPermission()` already added Day 1; write-routes check `pet_hr | pet_manager`

Done when: HR can add a service item; a sales user sees the catalog table but no edit controls.

---

### Day 5 — Employees Module + WP User Account Creation

**Goal:** HR can create staff records; each new employee gets a WordPress login automatically.

Tasks:
- Import `Employees.tsx` + `EmployeeForm.tsx` into portal Employees route
- Extend `EmployeeForm.tsx` with two new fields:
  - **Portal Role** dropdown: Sales / HR / Manager / No portal access
  - **Portal Email** — pre-filled from employee email, editable
- Backend: extend `EmployeeController::createEmployee()` to:
  - Call `wp_create_user()` + `wp_update_user()` + `add_cap()` + `wp_send_new_user_notifications()`
  - Persist `wp_user_id` to `pet_employees`
  - Return `wp_user_warning` in response if WP user creation fails (never blocks employee save)
- UI: show success toast "Employee created — login invitation sent to {email}"
- UI: show warning banner if `wp_user_warning` present in response

Done when: HR creates an employee with Portal Role = Sales → employee receives "set your password" email → can log in to portal.

---

### Day 6 — Leads Module

**Goal:** Sales staff can log leads against customers and track their status.

Tasks:
- `src/UI/Portal/PortalLeads.tsx` — leads list + create form (extracted from `Commercial.tsx` leads section)
- List columns: Customer, Description, Status badge (`new` / `qualified` / `quoted`), Created date, Assigned to
- Create form: customer picker (searchable dropdown), description, optional notes
- Lead detail: show linked quotes (status + total) as sub-list
- `LeadController` — `checkPortalPermission()` added Day 1

Done when: Sales staff can create a lead linked to a customer and see any attached quotes.

---

### Day 7 — Quote List + Approval Queue

**Goal:** Full quote status visibility for sales; approve/reject for managers.

Tasks:

**Quote list (sales + managers):**
- List view: Customer, Title, Version, Status badge, Total, Created date
- Status badges: `draft` (grey) | `pending_approval` (amber) | `approved` (green) | `sent` (blue) | `accepted` (teal)
- Click quote → Quote Detail: block summary table (read-only), totals, status history
- Actions on detail: Send (if `approved`), Mark Accepted (if `sent`), Download PDF

**Approval queue (managers only):**
- `src/UI/Portal/PortalApprovals.tsx`
- Filtered list of `pending_approval` quotes
- Shows: customer, quote title, total value, submitted by, submitted date
- Approval threshold indicators: ⚠️ if value > $5,000 or discount > 15%
- Approve button → confirmation dialog → `POST /pet/v1/quotes/{id}/approve`
- Reject button → dialog with rejection note textarea → `POST /pet/v1/quotes/{id}/reject`
- On action: quote removed from queue, toast shown, list refreshes

Done when: A pending quote submitted by sales is visible to the manager, who can approve or reject it from the portal.

---

### Day 8 — Quote Builder (Core Block Types)

**Goal:** Sales staff can create a new quote and add the four most common block types.

This is the highest-risk day. All block editor components already exist — the work is wiring them together in a portal context without the full `Commercial.tsx` admin shell.

Tasks:
- `src/UI/Portal/PortalQuoteEditor.tsx` — quote builder wrapper:
  - Quote header form: title, customer picker, version label
  - Block table with `BlockRow.tsx` for each existing block
  - "Add block" menu: Service | Hardware | Text | Price Adjustment
  - Inline row editors: `ServiceBlockEditor.tsx`, hardware equivalent, text, adjustment (all imported directly)
  - Save / reorder / delete per block (existing REST endpoints: `/pet/v1/quotes/{id}/blocks`)
  - Submit for Approval button (active when quote has ≥1 block and is in `draft` state)
- Route: `/portal/quotes/new` and `/portal/quotes/{id}/edit`
- Block types implemented Day 8:
  - `OnceOffSimpleServiceBlock` ✅
  - `HardwareBlock` ✅
  - `TextBlock` ✅
  - `PriceAdjustmentBlock` ✅

Done when: Sales staff can create a quote with service and hardware blocks and submit it for approval.

---

### Day 9 — Quote Builder (Project Blocks) + Submit Flow

**Goal:** Complete block coverage; full lifecycle from create to sent.

Tasks:
- Add `OnceOffProjectBlock` to quote builder (uses existing `ProjectBlockEditor.tsx`)
- Add `RepeatServiceBlock` + `RepeatHardwareBlock`
- Quote lifecycle actions from builder:
  - **Submit for Approval** → `POST /pet/v1/quotes/{id}/submit-for-approval` → status → `pending_approval`
  - **Mark Sent** (manager, after approval) → `POST /pet/v1/quotes/{id}/send`
  - **New Version** (if quote is in sent/accepted state) → creates new draft version
- Rejection note display on `draft` quote that was previously rejected (amber banner with note text)
- Quote builder navigation guard: "Unsaved changes — leave anyway?" on route change

Done when: Full quote lifecycle works portal-to-portal: create → blocks → submit → manager approves → mark sent.

---

### Day 10 — PDF Generation + QA Pass

**Goal:** PDF downloads cleanly; all role gates verified; end-to-end walkthrough complete.

Tasks:

**PDF endpoint:**
- `src/UI/Rest/Controller/QuotePdfController.php`
- `GET /pet/v1/quotes/{id}/pdf` — permission: `pet_sales | manage_options`
- TCPDF (via Composer) renders A4 layout:
  - Header: company name (from settings), "QUOTATION", quote ref, date
  - Bill To: customer name + address
  - Prepared By: employee name
  - Validity: 30 days from creation
  - Block table: Description | Qty | Unit | Unit Price | Total
  - Price adjustment line items
  - Grand Total (bold)
  - Footer: quote reference, T&Cs placeholder
- Portal "Download PDF" button fires `window.open(pdfUrl)` — browser handles download

**QA checklist:**
- [ ] Sales user: can see Customers, Catalog (read), Leads, Quotes — cannot see Employees, Approvals
- [ ] HR user: can see Employees, Catalog (write), Customers (read) — cannot see Leads, Quotes, Approvals
- [ ] Manager user: can see everything, Approvals queue functional
- [ ] Create employee → WP user created → email sent → login works
- [ ] Full quote lifecycle: create → blocks → submit → approve → send → PDF download
- [ ] Empty states render correctly on fresh data
- [ ] Toast notifications fire on all create/update/delete actions
- [ ] Portal inaccessible to logged-out users (redirect to WP login)
- [ ] Admin panel unaffected (existing `checkPermission()` untouched)

Done when: End-to-end walkthrough passes with one user per role.

---

## Delivery Checkpoints

| Day | Deliverable | Status |
|---|---|---|
| 1 | Portal shortcode mounts, capabilities registered | ✅ Done |
| 2 | Nav, routing, role detection working | ✅ Done (merged with Day 1) |
| 3 | Customers CRUD live — `CustomersPage.tsx` | ✅ Done |
| 4 | Catalog Items + Products — `CatalogPage.tsx` | ✅ Done |
| 5 | Employees + WP user provision — `EmployeesPage.tsx` + `/employees/provision` endpoint | ✅ Done |
| 6 | Leads — `LeadsPage.tsx` | ✅ Done |
| 7 | Quote list + Approval queue — `QuotesPage.tsx` + `ApprovalsPage.tsx` | ✅ Done |
| 8 | Quote builder — 4 block types — `QuoteBuilderPage.tsx` | ✅ Done |
| 9 | Project block (read-only view) + submit-for-approval from builder | ✅ Done |
| 10 | PDF — `QuotePdfController.php` (`GET /quotes/:id/pdf`) + portal print buttons | ✅ Done |

---

## Post-Sprint Backlog (v1.1+)

These items are confirmed requirements but deliberately deferred past go-live:

| Item | Notes |
|---|---|
| Customer-facing quote acceptance | Email with accept link; customer portal page |
| Automated PDF email sending | SMTP/SendGrid integration |
| Mobile layout | Below 768px breakpoint styling |
| Helpdesk from portal | Ticket create/view for staff |
| Time entry from portal | Staff time logging |
| Quote builder advanced features | Discount override workflow, rate card selector |
| Portal dashboard / KPI strip | Summary widgets on login landing page |
