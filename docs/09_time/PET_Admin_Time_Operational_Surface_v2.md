# PET Admin Time Operational Surface v2

**Target location:** `plugins/pet/docs/09_time/PET_Admin_Time_Operational_Surface_v2.md`

## 0. Purpose

This document defines the next PET package for the **admin time screen**.

The staff time capture surface now exists as a separate product surface. This package returns focus to the **admin time section** and upgrades it from a modernized table into a true **operational control surface**.

This is not a domain redesign.  
This is not a billing redesign.  
This is not a report-builder package.

It is a behavior-preserving enhancement of the admin time surface so that administrators, finance, and managers can use it as a practical **timesheet control tower**.

PET principles remain binding:

- APIs remain authoritative
- domain/application layers enforce legality
- no business logic in UI
- no source-of-truth changes
- no mutation from read-only summary surfaces
- additive rollout
- backward compatibility

---

# 1. Scope of This Work Package

## 1.1 Included

This package covers:

1. admin time summary cards / summary strip
2. meaningful filtering and slicing
3. semantic row rendering (names, badges, context)
4. anomaly / exception highlighting
5. stronger bulk action context surfaces
6. drill-through improvements to related entities
7. tests for behavior preservation and query consistency

## 1.2 Excluded

This package does **not** include:

- staff time capture redesign
- time-entry domain redesign
- billing export redesign
- predictive analytics
- reporting builder functionality
- chart-heavy dashboarding as the primary mode
- auth redesign
- changes to source-of-truth ownership

---

# 2. Problem Statement

The current admin time screen is now structurally modernized, but still behaves too much like a **data table** and not enough like an **operational surface**.

It still under-communicates:

- who is logging time
- what that time relates to
- what looks normal vs suspicious
- what needs admin attention
- how time relates to downstream finance and operations

The next step is to make the screen answer those questions immediately, while preserving the existing truth and workflows.

---

# 3. Operational Surface Principles

## 3.1 Summary before detail

The screen should first show:
- what is happening overall
- what needs attention
- how to narrow the view

Then show detailed entries.

## 3.2 Meaning before raw identifiers

The screen should prefer:
- employee names
- project/customer/ticket context
- semantic badges
- meaningful labels

over raw internal IDs wherever possible.

## 3.3 Filters as operational tools

Filtering is not cosmetic.
It is one of the main jobs of the admin time surface.

## 3.4 Exceptions must be surfaced

Potential problems should not require manual scanning of the whole table.
The screen should make exceptions easier to spot.

## 3.5 Table remains valid, but not dominant in meaning

This package does not remove the table.
It wraps the table in a stronger context and enriches what each row communicates.

---

# 4. Required Surface Improvements

## 4.1 Summary strip / cards

Add a visible summary surface that can communicate current context, such as:

- total hours in current view
- billable vs non-billable split
- number of entries
- number of distinct staff
- entries needing attention
- unbilled billable time if already safely available

The summary must derive from real API/query truth, not UI-only invention.

## 4.2 Filters

The admin time surface should support meaningful filters, at minimum where safe and supported by current data:

- employee
- team
- customer
- ticket / project context
- billable / non-billable
- date or date range
- “needs attention” / exception-oriented presets if supported

Filters must be server-authoritative where applicable.
Do not rely on client-only filtering for access or truth.

## 4.3 Semantic row rendering

Rows should show more useful meaning, for example:

- employee name instead of raw employee ID
- related ticket/project/customer context instead of raw IDs where available
- billable/non-billable badges
- duration with clearer visual hierarchy
- correction/adjustment indicators if already present in source data
- drill-through links to related entities where already safe

## 4.4 Attention / anomaly highlighting

The screen should visually surface entries or groups that may need review, for example if safely derivable:

- unusually large single entries
- non-billable anomalies
- suspiciously sparse or missing context
- entries linked to problematic states
- review-needed patterns already represented in truth

This package must not invent fake anomaly logic disconnected from available data.

## 4.5 Bulk action context

Bulk actions should be easier to understand in context.
The selected-items bar should feel like a deliberate review/action surface, not just an inline utility row.

## 4.6 Drill-through

Where already safe and supported, admin users should be able to navigate from time rows into relevant related surfaces such as:

- employee
- ticket
- project
- customer

This package does not require creating new destinations; it should reuse existing safe routes where possible.

---

# 5. Visual / UX Expectations

This package should build on the visual transformation work and move the admin time screen toward:

- stronger card/summary hierarchy
- clearer filter strip
- more meaningful badges/tags
- less dead-data feeling
- more product-like scanning experience
- less “raw log table” feel

This is not a full card-based replacement for the table.
The table remains the detailed operational layer.

---

# 6. Data and Behavior Constraints

## 6.1 No behavior changes

This package must not change:

- create/edit/archive behavior
- bulk archive semantics
- endpoint contracts
- domain legality
- admin permissions
- time-entry source truth

## 6.2 No fake semantics

Do not invent:
- fake employee names
- fake customer/project labels
- fake anomalies
- fake summary metrics

If data is not safely available, the plan must state that clearly.

## 6.3 Shared primitives remain mandatory

Use existing/shared PET UI primitives wherever possible:
- PageShell
- Panel / Card
- ActionBar
- DataTable
- StatusBadge
- LoadingState / EmptyState / ErrorState
- Dialog / ConfirmationDialog
- ToastProvider / useToast

No competing design system should be introduced.

---

# 7. Rollout Order for This Package

## Phase T1
Admin Time summary strip + stronger filters + semantic row rendering

## Phase T2
Attention/anomaly highlighting + stronger drill-through

## Phase T3
Billing-readiness overlay / finance-oriented context if clearly supported by current truth

This document only initiates the next package at the admin time surface level.

---

# 8. Tests Required

The package must add or update tests to ensure:

- no behavior changes
- existing admin time flows remain intact
- summary values come from real data/query results
- filters preserve correctness
- semantic rendering does not alter underlying values
- no native dialogs reappear
- drill-through hooks preserve existing route behavior where used

---

# 9. Prohibited Behaviours

- Must not redesign the time-entry domain.
- Must not weaken permissions.
- Must not replace the table with a less useful dense-data interaction model.
- Must not introduce fake dashboard metrics disconnected from query truth.
- Must not move business logic into UI.
- Must not turn this into a chart/report builder package.
- Must not change staff time capture behavior in this package.

---

# 10. Expected Outcome

If implemented correctly, the admin time screen should become:

- easier to scan
- easier to filter
- more meaningful
- better at surfacing attention items
- closer to a real operational control surface

without changing the underlying truth or workflows.
