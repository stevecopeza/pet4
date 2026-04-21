# 42 — Staff Portal

This section covers the RPM staff-facing portal at `pet.cope.zone/portal`.

## Documents

| File | Description |
|---|---|
| `01_staff_portal_architecture.md` | Technical architecture: URL, build config, design system, role model, permission layer, WP user creation, PDF generation |
| `02_sprint_plan_rpm_golive.md` | 10-day go-live sprint plan: scope, risks, day-by-day tasks, delivery checkpoints, post-sprint backlog |
| `03_ux_improvement_proposal.md` | UX improvement proposal: full-page detail routes, employee blueprint with linked-data tabs, mobile strategy, test plan — **AWAITING APPROVAL** |

## Status

**COMPLETE — All 10 days delivered 2026-04-20**

- Portal URL: `pet.cope.zone/portal`
- Architecture: Single `[pet_portal]` shortcode → portal-native React SPA (separate from admin components)
- Build: Vite dual-entry (admin + portal); all portal pages lazy-loaded as separate chunks

## What Was Built

| Page | File | Capabilities |
|---|---|---|
| Customers | `src/UI/Portal/pages/CustomersPage.tsx` | List, create, edit, archive; contacts sub-view |
| Catalog | `src/UI/Portal/pages/CatalogPage.tsx` | Services + products tabs; HR/manager write, sales read |
| Employees | `src/UI/Portal/pages/EmployeesPage.tsx` | Provision (creates WP user + grants cap); edit; archive |
| Leads | `src/UI/Portal/pages/LeadsPage.tsx` | List, create, edit, status filter, pipeline KPIs; convert to quote |
| Quotes | `src/UI/Portal/pages/QuotesPage.tsx` | List, create, detail view, submit for approval |
| Approvals | `src/UI/Portal/pages/ApprovalsPage.tsx` | Manager-only; approve/reject with notes |
| Quote Builder | `src/UI/Portal/pages/QuoteBuilderPage.tsx` | Block canvas + right panel; 4 block types; sections; PDF link |

## Key PHP additions

| File | Purpose |
|---|---|
| `src/UI/Rest/Support/PortalPermissionHelper.php` | `check(string ...$caps): bool` — shared permission gate |
| `src/UI/Portal/Shortcode/PortalShortcode.php` | `[pet_portal]` shortcode; asset enqueue; `window.petSettings` injection |
| `src/UI/Rest/Controller/QuotePdfController.php` | `GET /pet/v1/quotes/:id/pdf` — print-ready HTML; no external deps |
| `src/UI/Rest/Controller/EmployeeController.php` | + `POST /employees/provision` — creates WP user + grants portal cap atomically |

## QA Checklist (run before go-live)

- [ ] Sales user: Customers ✓, Catalog (read-only) ✓, Leads ✓, Quotes ✓ — no Employees or Approvals
- [ ] HR user: Employees ✓, Catalog (write) ✓, Customers ✓ — no Leads, Quotes, Approvals
- [ ] Manager user: all sections ✓, Approvals queue functional ✓
- [ ] Provision employee → WP user created → portal login works
- [ ] Full quote lifecycle: create → add blocks → submit → approve → PDF download
- [ ] Empty states render on fresh data
- [ ] Portal inaccessible to logged-out users (WP login redirect shown)
- [ ] Admin panel unaffected
