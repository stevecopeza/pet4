STATUS: IMPLEMENTED
SCOPE: Admin Time Entries Operational Surface
VERSION: v1.0

# Admin Time Entries — Operational Surface (v1)
## Purpose
Defines the authoritative behavior of the admin Time Entries screen (`pet-time`) used to review, filter, discuss, and archive time entries.
This document covers the admin list surface only. Ticket-detail work-log rendering is specified separately in `06_Time_Entry_Work_Log_Display_v1.md`.

## Data Sources
- `GET /pet/v1/time-entries` (authoritative list and filtering)
- `GET /pet/v1/employees` (employee display enrichment and filter labels)
- `GET /pet/v1/tickets` (ticket subject + customer/site linkage)
- `GET /pet/v1/customers` (customer name enrichment)
- `GET /pet/v1/sites` (site name enrichment)
- `GET /pet/v1/conversations/summary?context_type=time_entry&context_ids=...` (row-level conversation status indicators)

Lookup fetches are additive-only enrichment and are non-blocking to list usability.

## Filter and Query Contract
- Employee filter is a dropdown populated from employee IDs present in currently loaded entries.
- Ticket filter is numeric input.
- Filter submission is authoritative through query params:
  - `employee_id`
  - `ticket_id`
- Clear Filters resets both controls and clears row selection state.

## Summary Strip
Computed from currently loaded entries only:
- Entries
- Total Logged
- Billable (minutes + percentage)
- Non-billable
- Distinct Staff
- Corrections

## Table Rendering Contract
## Core Columns
- ID
- Employee
- Ticket
- Customer / Site (combined)
- Start
- End
- Duration
- Description
- Actions

## Compact Operational Indicators
Three compact icon-only columns are rendered between Description and Actions:
- Billable icon
- Status icon
- Correction icon

Column headers for these indicator columns are intentionally blank to maximize horizontal space.

## Indicator Semantics
- Billable:
  - `$` green = billable
  - `$` neutral = non-billable
  - Tooltip text format: `Billable: <state>`
- Status:
  - `●` color maps by status class (`draft`, `submitted`, `approved`, `locked`, `rejected`, fallback neutral)
  - Tooltip text format: `Status: <status>`
- Correction:
  - `↺` = correction entry
  - `•` = original entry
  - Tooltip text format: `Correction: <state>`

Tooltips are exposed via both `title` and custom `data-tooltip` attributes for reliable hover behavior.

## Conversation Row Indicator
- A clickable conversation status dot may appear in the ID cell.
- Dot is shown only when conversation summary status is not `none`.
- Dot color map:
  - `red` → `#dc3545`
  - `amber` → `#f0ad4e`
  - `green` → `#28a745`
  - `blue` → `#007bff`
- Clicking the dot opens discussion context:
  - `contextType: "time_entry"`
  - `contextId: <timeEntryId>`
  - `subjectKey: "time_entry:<id>"`

Kebab actions also expose `Discuss` per row.

## Enrichment and Fallback Rules
- Employee:
  - Preferred: `displayName`
  - Fallback: employee ID
- Ticket:
  - Preferred: `#<id> · <subject>`
  - Fallback: ticket ID
- Customer / Site:
  - If ticket missing: `—`
  - Customer preferred name, fallback `Customer <id>`
  - Site preferred name, fallback `Site <id>`
  - Combined format: `<Customer> · <Site>`
- Conversation summaries:
  - Missing summary yields no row dot.

## Date/Time Display
- Start and End are compact-formatted (no year, no seconds).
- Intended to reduce column width pressure while preserving readability.

## Actions and Bulk Operations
- Per-row actions:
  - Edit
  - Discuss
  - Archive
- Bulk selection supports `Archive Selected` with confirmation dialog.
- Archive operations update list and maintain toast-based user feedback.

## UX and Accessibility Notes
- Indicator icons expose `aria-label` text for semantic meaning.
- Conversation dot is keyboard-focusable and clickable.
- List remains usable when enrichment endpoints fail.

## Validation Baseline
Covered by:
- `src/UI/Admin/__tests__/TimeEntries.t1a.test.tsx`
- `src/UI/Admin/__tests__/Phase2.modernization.test.tsx`

These tests validate summary calculations, query-parameter filtering, semantic/fallback rendering, discuss affordances, conversation-dot behavior, tooltip attributes, and compact date formatting invariants.
