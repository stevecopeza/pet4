# PET – WordPress Menu and Navigation Structure

## Purpose of this Document
This document defines how PET is surfaced within WordPress Admin, including:
- top-level menu placement
- submenu structure
- navigation flow between functional areas
- dashboard and operational entry points

This is the normative navigation contract for PET admin surfaces.

---

## Design Principles
- one PET top-level entry only
- stable route names for predictable navigation
- functional grouping over technical grouping
- dashboard and benchmark surfaces are read-focused entry points

---

## Top-Level Menu
Menu title: `PET`
- icon: `dashicons-chart-area`
- admin menu position: `25`

---

## Implemented Submenu Structure
```
PET
├─ Overview
├─ Dashboards
├─ My Work
├─ My Profile
├─ Customers
├─ Quotes & Sales
├─ Finance
├─ Delivery
├─ Time
├─ Support
├─ Conversations
├─ Advisory
├─ Performance
├─ Approvals
├─ Knowledge
├─ Staff
├─ Roles & Capabilities
├─ Activity
├─ Settings
├─ Pulseway RMM
├─ Shortcodes
└─ Demo Tools
```

Feature-gated submenu:
- `Escalations` is conditionally added when escalation feature gating is enabled.

Staff internal tabs (inside `Staff` page):
- Org
- Teams
- People

When `pet_staff_setup_journey_enabled` is active, People view includes setup journey guidance and readiness-driven actions.

---

## Landing Behaviour
### PET → Overview
Overview is role-aware:
- executives: high-level operational KPIs
- managers: delivery/people emphasis
- individual contributors: personal work context

---

## Dashboard and Benchmark Flow
- `PET → Dashboards` opens role/persona dashboard surfaces (read-focused)
- `PET → Performance` opens performance benchmark diagnostics (admin-only, benchmark run capable)
- Project Manager cards inside `PET → Dashboards` drill through to Delivery project detail using `PET → Delivery` with `#project=<id>` URL state.

Neither route is used for direct domain-record mutation.

---

## Functional Flow Contracts
### CRM
`Leads → Qualification → Opportunities → Quotes`

### Customer Setup
`Customer → Branches → Contacts → Ready`

Context continuity is required across Customer, Branch, and Contact interactions.

### Quotes & Sales
`Quotes → Acceptance → Sales → Delivery Projects`

### Delivery
`Projects List → Project Detail Workspace (Tickets) → Time`

### Support
`Tickets → Resolution → Knowledge`

---

## Settings Isolation
Settings remain isolated from day-to-day operational workflows and are restricted by role/capability.

---

**Authority**: Normative
