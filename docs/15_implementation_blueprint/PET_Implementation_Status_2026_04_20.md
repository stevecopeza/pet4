# PET Implementation Status — 20 April 2026

**Status:** AUTHORITATIVE
**Scope:** Records the implementation state of all work completed in the 20 April 2026 session.
**Base status:** `PET_Implementation_Status_2026_03_23_Staff_Journey_Seed_Hardening_Addendum.md`

---

## 1. Staff Portal — COMPLETE

All 10 days of the RPM go-live sprint plan delivered. Full spec: `docs/42_staff_portal/`.

### Architecture

- Single `[pet_portal]` shortcode → React SPA, separate Vite entry (`src/UI/Portal/main.tsx`)
- Hash-based client-side routing; `builderMode` flag for full-screen quote builder
- 3 WP custom capabilities: `pet_sales`, `pet_hr`, `pet_manager` (per-user, not per-role)
- `PortalPermissionHelper::check(string ...$caps): bool` — shared permission gate (OR-match; always passes `manage_options`)
- `window.petSettings` injection: `apiUrl`, `nonce`, `currentUserId`, `currentUserDisplayName`, `currentUserCaps[]`, `logoutUrl`
- Portal-native design system: `portal-*` CSS classes, viewport-anchored layout escaping WP theme containers

### New PHP files

| File | Purpose |
|---|---|
| `src/UI/Rest/Support/PortalPermissionHelper.php` | Shared `check(string ...$caps): bool` gate |
| `src/UI/Portal/Shortcode/PortalShortcode.php` | `[pet_portal]` shortcode; asset enqueue; `window.petSettings` injection |
| `src/UI/Rest/Controller/QuotePdfController.php` | `GET /pet/v1/quotes/:id/pdf` — print-ready HTML; no external deps |
| `src/UI/Rest/Controller/EmployeeController.php` | Extended with `POST /employees/provision` — creates WP user + grants portal cap atomically |

### New TypeScript/React files

| File | Purpose |
|---|---|
| `src/UI/Portal/main.tsx` | Portal SPA entry point |
| `src/UI/Portal/PortalApp.tsx` | Hash router; `builderMode` detection |
| `src/UI/Portal/PortalShell.tsx` | Fixed sidebar/header/main layout |
| `src/UI/Portal/hooks/usePortalUser.ts` | Reads `window.petSettings.currentUserCaps` |
| `src/UI/Portal/portal.css` | Full portal design system; admin bar offset handling |
| `src/UI/Portal/pages/CustomersPage.tsx` | List + KPI strip + slide panel; contacts sub-view; affiliation filter |
| `src/UI/Portal/pages/CatalogPage.tsx` | Services + Products tabs; `canEdit` gate by capability |
| `src/UI/Portal/pages/EmployeesPage.tsx` | `ProvisionForm` with portal role assignment; `EmployeeAvatar` |
| `src/UI/Portal/pages/LeadsPage.tsx` | Pipeline value KPI; 5 status filter tabs; convert-to-quote action |
| `src/UI/Portal/pages/QuotesPage.tsx` | 4-KPI strip; pending approval banner; `QuoteDetailPanel`; PDF + builder links |
| `src/UI/Portal/pages/ApprovalsPage.tsx` | `ApprovalCard` per pending quote; inline reject textarea |
| `src/UI/Portal/pages/QuoteBuilderPage.tsx` | Split canvas + editor panel; block type forms; `QuoteSummaryBar`; section CRUD |

### Role/capability gating

| Section | pet_sales | pet_hr | pet_manager |
|---|---|---|---|
| Customers | ✅ read/write | ✅ read/write | ✅ read/write |
| Catalog | ✅ read-only | ✅ read/write | ✅ read/write |
| Employees | ❌ | ✅ full | ✅ full |
| Leads | ✅ full | ❌ | ✅ full |
| Quotes | ✅ full | ❌ | ✅ full |
| Approvals | ❌ | ❌ | ✅ only |

### Outstanding (v1.1 backlog)

- Customer-facing quote acceptance (email link, customer portal page)
- Automated PDF email sending
- Mobile layout optimisation
- Helpdesk from portal
- Time entry from portal
- Quote builder: discount override workflow, rate card selector
- Portal dashboard / KPI landing page

### Outstanding (UX / quality — raised 2026-04-20)

These are improvements to what was built, not new features:

| Item | Detail |
|---|---|
| **UX: list/detail pattern** | Current 2/3 list + 1/3 slide panel is too narrow for rich records. Proposal: full-page detail routes for Customers, Employees, Leads, Quotes; modals for create/quick-edit. |
| **UX: Employees linked data tabs** | Portal employee detail should have tabs: Identity, Roles, Skills, Certifications, Reviews — mirroring the admin panel. Admin already has these components. |
| **UX: mobile** | Full-page detail routes would make mobile viable. Current slide-panel layout is not mobile-friendly. Needs responsive breakpoints once pattern is chosen. |
| **Tests: portal E2E** | Zero portal tests exist. Need Playwright test suite covering: smoke (all portal routes load per role), and user journey tests mirroring navigation: Customers CRUD, Employee provision → login, Lead → Quote conversion, Quote → approve → PDF. |
| **Tests: auth fixtures** | Need portal-specific Playwright auth fixtures for pet_sales, pet_hr, pet_manager users (separate from admin .auth/admin.json). |

---

## 2. Ticket Backbone — Application Layer Confirmed Complete

Investigation conducted 2026-04-20 confirmed that the ticket backbone application layer was already implemented prior to this session. Documentation was stale. Corrected across 5 documents (see below).

### What is implemented

| Component | Status | Detail |
|---|---|---|
| `AcceptQuoteHandler::createTicketsFromQuote()` | ✅ Done | Calls `CreateProjectTicketHandler` for each labour item |
| `CreateProjectTicketHandler` | ✅ Done | Creates `Domain\Support\Entity\Ticket` with all backbone fields |
| `CreateProjectFromQuoteListener` | ✅ Done | Creates **Project only** — no Tasks. Has explicit guard comment. |
| `AddTaskHandler` | ✅ Disabled | Throws `DomainException('Legacy project task creation is disabled')` |
| `LogTimeHandler` | ✅ Done | Enforces `canAcceptTimeEntries()` against Tickets |
| `WorkItemProjector` | ✅ Done | `onTicketCreated()` handles project tickets; no `onProjectTaskCreated` |
| Rollup ticket pattern | ✅ Done | Multi-task components get a rollup + child Tickets |
| Idempotency | ✅ Done | `findByProvisioningKey()` prevents duplicates on re-acceptance |

### Ticket backbone fields set on acceptance

- `soldMinutes` — locked from `task.durationHours() * 60`
- `soldValueCents` — locked
- `estimatedMinutes` — same as `soldMinutes` at creation
- `isBaselineLocked = true`
- `isRollup` — true for rollup tickets, false for leaf tickets
- `parentTicketId` — set on child tickets referencing the rollup
- `projectId` — set for all delivery tickets
- `quoteId` — set for all delivery tickets
- `lifecycleOwner = 'project'`
- `primaryContainer = 'project'`
- `billingContextType = 'project'`
- `status = 'planned'` (project lifecycle initial state)
- `intakeSource = 'quote'`
- `malleableData.source = 'quote'`, `malleableData.quote_id = ...`

### Remaining ticket backbone work

| Item | Phase | Priority |
|---|---|---|
| WBS post-acceptance splitting (parent/child hierarchy) | — | Medium |
| Admin project UI cutover (`Project.tasks: Task[]` → Tickets) | Phase 7 | Medium |
| `wp_pet_tasks` backfill for historical rows | Phase 2 | Low |
| SLA agreement/entitlement for delivery tickets | Phase 6 | Low |
| Remove dead code (`Task.php`, `AddTaskHandler`, `AddTaskCommand`) | Phase 8 | Low |
| Drop `wp_pet_tasks` table | Phase 8 | Low (after Phase 7 stable) |

---

## 3. Documentation Corrections Made

The following documents were updated to remove stale Task/Ticket claims:

| Document | Change |
|---|---|
| `MEMORY.md` | Fixed Delivery domain entry; corrected Known Gaps 1 & 3; added Staff Portal section |
| `PET_Lifecycle_Gap_Analysis_v1_0.md` | Updated to v1.1; corrected executive summary, Sections 1, 3, 5, 7, and appendix |
| `Ticket_Backbone_Planning_State_v1.md` | Updated to v3; marked Phase 4 complete; updated drift summary and phase statuses |
| `PET_Ticket_Backbone_Implementation_Roadmap_v1.md` | Updated to v3; marked Phases 0–5 complete; expanded Phase 7/8 descriptions |
| `03_domain_model/01_entities_overview.md` | Updated Task (dead code note), WorkItem (removed `project_task` source type), Lead (Customer requirement gap noted), Quote (acceptance triggers updated) |

---

## 4. Summary of Outstanding Work (Priority Order)

1. **Admin UI — SupportOperational.tsx** — backend complete, UI is a stub. Queue-first workflow, SLA colour coding, manager panels. Highest user-facing impact.
2. **Admin UI — Advisory.tsx** — signal list, report list, generation trigger. Medium impact.
3. **Admin UI — WorkItems.tsx polish** — item-level actions (pick up, reassign), drill-through, human-readable labels.
4. **Ticket backbone — WBS splitting** — post-acceptance child ticket creation. Required before project-level delivery tracking is meaningful.
5. **Ticket backbone — Admin project UI (Phase 7)** — replace `Project.tasks: Task[]` with delivery tickets in admin panel.
6. **CRM — Lead without Customer** — remove hard `customerId` requirement from `CreateLeadCommand`.
7. **Portal v1.1** — customer-facing acceptance, email sending, mobile layout (deferred per sprint plan).
8. **Legacy cleanup (Phase 8)** — remove dead Task code and `wp_pet_tasks` table. Low urgency.
