STATUS: IMPLEMENTED
SCOPE: Time Entry Presentation and Creation in Ticket Detail View
VERSION: v1.1

# Time Entry — Work Log Display (v1)

## Relationship to Enforcement Rules

This document covers **rendering only**. Domain rules for time entry creation, submission, and lock are governed by `05_Time_Entry_Ticket_Enforcement_v1.md` (AUTHORITATIVE).

The Work Log section displays all time entries for a ticket and provides inline creation and draft editing capabilities.

## Purpose

The Work Log is the primary "what got done" section on a ticket detail view. It shows all time entries logged against a ticket, providing visibility into:

- Who performed work
- When and for how long
- What was done (description)
- Whether it was billable
- Entry lifecycle status (draft / submitted / locked)

## API Endpoints

### Read
```
GET /pet/v1/time-entries?ticket_id={ticketId}
```

Returns `TimeEntryItem[]`:
- `id` (int)
- `employeeId` (int)
- `ticketId` (int)
- `start` (datetime string)
- `end` (datetime string)
- `duration` (int, minutes)
- `description` (string)
- `billable` (boolean)
- `status` (string: draft | submitted | locked)
- `correctsEntryId` (int, nullable — set when this is a compensating entry)
- `isCorrection` (boolean)
- `createdAt` (datetime string, nullable)

### Create
```
POST /pet/v1/time-entries
```
Body: `{ employeeId, ticketId, start, end, isBillable, description }`
Returns: `{ id, ...entry }` — created as `draft` status.

### Update Draft
```
PUT /pet/v1/time-entries/{id}
```
Body: `{ description, start, end, isBillable }`
Domain guard: rejects if entry status ≠ `draft`.

### Correct (Compensating Entry)
```
POST /pet/v1/time-entries/{id}/correct
```
Creates a reversal (negative duration) and a corrected re-log referencing the original entry via `corrects_entry_id`.

## Display Specification

### Summary Strip

Three statistics rendered at the top of the Work Log section:
- **Total hours**: sum of all entry durations, formatted as `Xh`
- **Billable hours**: sum of durations where `billable=true`, formatted as `Xh`
- **Entry count**: total number of entries

### Entry List

Sorted by `start` descending (newest first).

Each entry card shows:

1. **Avatar** — first letter of employee name, green gradient background
2. **Header row**:
   - Employee name (resolved via employees API)
   - Status badge: `draft` (yellow), `submitted` (green), `locked` (blue)
   - Billable tag (teal, shown only when `billable=true`)
3. **Description** — the work entry description text
4. **Meta row**:
   - Duration (highlighted, e.g. "1h 30m" or "45m")
   - Date and time range (e.g. "Mon, Feb 24 • 09:00 – 10:30")

### Correction Badge

Entries where `isCorrection=true` display a correction indicator badge. Submitted/locked entries show a ↺ "Correct" action button.

### Log Work Form

Triggered by "+ Log Work" button in the Work Log section header.

Inline form fields:
- **Description** (textarea, required)
- **Duration** (hours + minutes inputs, required)
- **Billable** toggle (default derived from ticket's `isBillableDefault`)

On submit: `POST /pet/v1/time-entries` with `ticketId` from current ticket, `employeeId` from current user. New entry appears immediately (optimistic UI).

### Draft Editing

Draft entries created by the current user display an edit icon. Clicking opens the same form pre-populated with existing values. On save: `PUT /pet/v1/time-entries/{id}`.

### Empty State

When no time entries exist for the ticket: "No time entries logged yet."

## CSS Classes

All classes use the `pd-worklog-` prefix:

- `.pd-worklog-summary` — summary strip container
- `.pd-worklog-stat`, `.pd-worklog-stat-value`, `.pd-worklog-stat-label`
- `.pd-worklog-list` — entry list container
- `.pd-worklog-entry` — individual entry card (blue left border)
- `.pd-worklog-avatar` — employee initial circle
- `.pd-worklog-body`, `.pd-worklog-header`, `.pd-worklog-author`
- `.pd-worklog-tags` — status + billable badge container
- `.pd-worklog-status` with modifiers `.status-draft`, `.status-submitted`, `.status-locked`
- `.pd-worklog-billable` — billable indicator badge
- `.pd-worklog-desc` — description text
- `.pd-worklog-meta`, `.pd-worklog-duration`, `.pd-worklog-date`

## Employee Name Resolution

Time entries use `employeeId` (the PET employee table ID). Resolution order:

1. Match `employees.id` or `employees.wpUserId` against `entry.employeeId`
2. If matched: display `{firstName} {lastName}`
3. Fallback: `Employee #{employeeId}`

## Invariants

- `ticketId` and `employeeId` are immutable from creation
- Only draft entries may be edited, and only by the creating employee
- Submitted/locked entries are immutable — corrections use compensating entries
- No filtering or pagination in v1 (all entries for the ticket are shown)
- Duration is pre-computed server-side and displayed as-is
- Status badges are display-only — no status transitions from the Work Log UI

## File Location

- Component: `src/UI/Admin/components/Dashboards.tsx` (within `TicketDetailPanel`)
- Styles: `src/UI/Admin/dashboard-styles.css` (`.pd-worklog-*` section)
