# PET – Ticket Detail View UI Contract v1.0

Status: **IMPLEMENTED**
Date: 2026-03-02

## Purpose

Defines the layout, data requirements, and rendering contract for the Ticket Detail panel — the drill-down surface accessed by clicking a ticket card in the Support persona view of the admin dashboard SPA.

This is a companion to `docs/24_support_helpdesk/PET_Helpdesk_Overview_UI_Contract_v1_0.md` (which covers the overview shortcode).

---

## Entry Point

From the Support dashboard view, clicking any `AttentionCard` (my tickets or unassigned queue) sets `selectedTicketId` state, which renders the `TicketDetailPanel` component in place of the `SupportView`.

Back navigation: a "← Back to Support Dashboard" button clears `selectedTicketId`.

---

## Data Loading

On mount, the panel fetches all detail data in parallel:

```
Promise.all([
  GET /pet/v1/customers?id={customerId}
  GET /pet/v1/work-items/by-source?source_type=ticket&source_id={ticketId}
  GET /pet/v1/employees
  GET /pet/v1/conversations?context_type=ticket&context_id={ticketId}&limit=50
  GET /pet/v1/time-entries?ticket_id={ticketId}
])
```

All calls degrade gracefully — if any fails, the section shows a fallback or empty state.

---

## Layout Structure

### Header

- Back button
- Ticket subject (h2)
- Meta row: `#{id}` + status badge + priority badge + category badge (if present)
- Opened date (right-aligned)

### SLA KPI Strip (4–5 tiles)

- SLA Time Remaining (colour-coded: red < 0, amber < 60m, green > 60m)
- Priority Score (from WorkItem)
- Response Due (time only)
- Resolution Due (time only)
- Active Signals count (only if signals present)

### Two-Column Grid

**Left column (main content):**

1. **Description** — ticket body text or "No description provided"
2. **Work Log** — time entries for this ticket (see `docs/04_time/06_Time_Entry_Work_Log_Display_v1.md`)
3. **Discussion** — conversation thread from `MessagePosted` timeline events
4. **Activity** — filtered feed events for ticket-related event types

**Right column (sidebar):**

1. **Assignment** — assigned employee name, department, work item status
2. **Customer** — name, email, status
3. **SLA Detail** — response due, resolution due, time remaining (with colour), resolved/closed timestamps
4. **Details** — ticket mode, intake source, subcategory, created date
5. **Advisory Signals** — severity-coloured cards with type + message (only if present)

---

## Data Contracts

### TicketItem (passed from parent)

Required fields: id, subject, status, priority, customerId, assignedUserId, createdAt
Optional fields: description, category, subcategory, ticketMode, siteId, slaId, contactId, openedAt, resolvedAt, closedAt, intake_source, sla_status, response_due_at, resolution_due_at

### TicketDetailData (fetched on mount)

- `customer`: { id, name, contactEmail?, status? } | null
- `workItem`: WorkItem | null
- `employees`: { wpUserId, firstName, lastName, id? }[]
- `conversations`: { uuid, subject, timeline: { id, type, payload, occurred_at, actor_id }[] }[]
- `timeEntries`: TimeEntryItem[] (see Time Entry Work Log spec)

### WorkItem (from work orchestration projection)

- id, source_type, source_id, assigned_user_id, department_id
- priority_score, status, sla_time_remaining, due_date
- signals: { type, severity, message }[]

---

## CSS Classes

All classes use the `pd-ticket-` prefix within `.pet-dashboards-fullscreen`:

- `.pd-ticket-detail` — root container
- `.pd-ticket-back` — back button
- `.pd-ticket-header`, `.pd-ticket-header-left`, `.pd-ticket-header-right`
- `.pd-ticket-title`, `.pd-ticket-meta`, `.pd-ticket-id`
- `.pd-ticket-badge` with modifiers: `.status-{status}`, `.priority-{priority}`, `.cat`
- `.pd-ticket-grid` — two-column layout
- `.pd-ticket-main` — left column
- `.pd-ticket-sidebar` — right column
- `.pd-ticket-section`, `.pd-ticket-field`, `.pd-ticket-field-label`, `.pd-ticket-field-value`
- `.pd-ticket-description`
- `.pd-conversation-thread`, `.pd-conversation-msg`, `.pd-msg-avatar`, `.pd-msg-body`, `.pd-msg-header`, `.pd-msg-author`, `.pd-msg-time`, `.pd-msg-text`
- `.pd-signal-list`, `.pd-signal-item`, `.pd-signal-type`, `.pd-signal-message`
- `.pd-worklog-*` classes (see Work Log spec)

---

## Behaviour Rules

- Panel is **read-only** — no mutation endpoints called
- Employee name resolution: matches by `wpUserId`, falls back to `currentUserId` → "You", then "User #N"
- Conversation messages: filtered to `type === 'MessagePosted'`, sorted by id ascending (chronological)
- Activity: filtered to ticket-related event types
- SLA colour coding: red (< 0), amber (0–60m), green (> 60m), grey (null)

---

## Responsive

At viewport ≤ 768px:
- Grid collapses to single column
- Header stacks vertically

---

## File Location

`src/UI/Admin/components/Dashboards.tsx` — `TicketDetailPanel` component (lines ~537–875)

---

**Authority**: Implementation record (normative for current state)
