# PET – Admin Dashboard SPA Implementation Spec v1.0

Status: **IMPLEMENTED**
Date: 2026-03-02

## Purpose

Documents the React SPA admin dashboard that delivers operational visibility across five persona-driven views. This is the primary operational surface within PET's WordPress admin, rendering at `/wp-admin/admin.php?page=pet-dashboards`.

This document is additive to the high-level dashboard principles in `10_dashboards/01_dashboard_and_kpi_views.md` and the per-persona focus areas in `10_dashboards/02_executive_dashboard.md` through `10_dashboards/06_people_dashboard.md`.

---

## Technology

- **Framework**: React 18 (TypeScript)
- **Build**: Vite 5 + TypeScript strict mode
- **Entry point**: `src/UI/Admin/main.tsx`
- **Component**: `src/UI/Admin/components/Dashboards.tsx`
- **Styles**: `src/UI/Admin/dashboard-styles.css`
- **Output**: `dist/assets/main-*.js` + `dist/assets/main-*.css` (Vite manifest at `dist/.vite/manifest.json`)

WordPress enqueues the built assets on the `pet-dashboards` admin page. WP admin chrome is hidden via CSS to provide a full-screen application experience.

---

## Architecture

The dashboard is a single React component (`Dashboards`) that:

1. Fetches all data on mount via existing PET REST API endpoints
2. Renders one of five persona views based on tab selection
3. Auto-refreshes every 60 seconds
4. Supports drill-down from Support view into a Ticket Detail panel
5. Supports drill-through from Project Manager cards into Delivery project detail workspace

### Data Flow

```
mount → loadAllData() → Promise.all([
  GET /pet/v1/dashboard       → overview KPIs
  GET /pet/v1/tickets         → all tickets
  GET /pet/v1/work-items      → all work items
  GET /pet/v1/projects        → all projects
  GET /pet/v1/activity        → feed events
  GET /pet/v1/customers       → customer names
])
```

All API calls use `window.petSettings.apiUrl` and `window.petSettings.nonce` for authentication.

### Invariants

- The dashboard performs **no mutation**. It is strictly read-only.
- No domain logic in the UI layer — all computation is display formatting (time formatting, percentage calculation, sort ordering).
- No SLA recalculation — SLA time remaining is read directly from WorkItem projection.

---

## Persona Views

### Manager — "Am I in control?"

**KPI Strip** (6 tiles):
- Revenue MTD
- Active Projects
- SLA Health (% of open tickets not breached)
- Utilisation
- Open Tickets
- Pending Quotes

**Needs Attention** panel:
- Breached tickets (severity: breached, pulse animation)
- SLA warning tickets (< 60 min remaining)
- Unassigned tickets
- Sorted: breached → warning → unassigned

**Strategic Activity** stream:
- Filtered to: quote_accepted, contract_created, project_created, milestone_completed, escalation_triggered, quote_sent, sla_breached, or severity=critical

### Support — "What should I do next?"

**KPI Strip** (4 tiles):
- My Open Tickets
- Breached (Mine)
- Due Within 1hr
- Unassigned Queue

**My Tickets by SLA Urgency**:
- Current user's assigned tickets, sorted by `sla_time_remaining` ascending (most urgent first)
- Each card is clickable → opens Ticket Detail panel
- Status badges: BREACHED / DUE SOON / ON TRACK

**Unassigned Queue**:
- Work items with no `assigned_user_id`, clickable
- Shown only when queue is non-empty

**Ticket Activity** stream:
- Filtered to ticket-related event types

### Project Manager — "Are we on track?"

**KPI Strip** (5 tiles):
- Active Projects
- Total Sold Hours
- Hours Used
- Budget Burn %
- Overdue Tasks

**Projects at Risk**:
- Over budget (burn > 100%)
- At risk (burn > 80%)
- Overdue (end date past)
- Card click-through opens Delivery detail route for that project (`?page=pet-delivery#project=<id>`)

**Delivery Activity** stream:
- Filtered to project-related event types

---

## Implementation Addendum (2026-03-25)

- PM project cards are keyboard and click navigable and route to Delivery via `?page=pet-delivery#project=<id>`.
- Delivery hash deep-link handling opens detail workspace mode for the selected project and shows `Back to Projects`.
- Initial loading preserves hash-selected project state so deep-linked project detail is not dropped before project data loads.

---

## Shared Sub-Components

- `KpiCard`: value + label + colour modifier
- `AttentionCard`: subject + meta + severity badge + timer + optional onClick (clickable in Support view)
- `ActivityStream`: chronological event list with severity icons

---

## CSS Design System

All classes are namespaced under `.pet-dashboards-fullscreen` and use the `pd-` prefix.

Key tokens:
- Background: `#f5f6fa` (base), `#ffffff` (cards)
- Accent colours: green/blue/amber/red/purple/teal (mapped via class modifiers)
- Border radius: `12px` (cards), `10px` (KPIs)
- Typography: System font stack

---

## File Locations

- Component: `src/UI/Admin/components/Dashboards.tsx`
- Styles: `src/UI/Admin/dashboard-styles.css`
- Entry: `src/UI/Admin/main.tsx`
- Build config: `vite.config.ts`, `tsconfig.json`
- WP registration: `src/UI/Admin/DashboardPage.php`

---

**Authority**: Implementation record (normative for current state)
