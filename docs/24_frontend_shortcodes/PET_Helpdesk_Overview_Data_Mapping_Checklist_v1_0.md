# PET Helpdesk Overview Data Mapping Checklist v1.0

Use this to map existing PET ticket + SLA read model fields to the `[pet_helpdesk]` UI.

## 1) Locate ticket list sources
Search codebase for:
- `TicketController`
- ticket list queries / repositories
- SLA state repository/projection (e.g. `pet_sla_clock_state`, `breach_at`, `next_due_at`, `last_evaluated_at`)

## 2) Minimum render contract
Map these fields (gracefully degrade if optional fields missing):
- ticket_id (required)
- ticket_ref like `#1042` (required)
- title/subject (required)
- customer_name (required)
- site_name (optional)
- status (required)
- priority (required)
- assignee_display (optional)
- queue/team_display (optional)
- is_breached OR breach_at (preferred)
- next_due_at (preferred)
- time_to_due_seconds (optional; may be computed from next_due_at for formatting only)
- is_escalated (optional)

## 3) Team scoping for `team=` attribute
Choose the existing concept PET uses today (do not invent):
A) Assignment queue/team on the ticket (preferred)
B) Assignee’s team membership
C) Department/category field

If none exist, `team` attribute becomes a no-op but must not break rendering.

## 4) Flow panel grouping key
Flow requires a stable key per queue/team.
If unavailable, hide the Flow panel (and keep `show_flow` attribute working).

## 5) SLA bands input availability
If SLA data absent:
- hide SLA KPIs (<30m, <2h, breached counts)
- hide “Due in …” / “Overdue …” badges
- still show Open/Status/Priority

## 6) Oldest ticket age
Compute from `opened_at` / `created_at` (preferred) for wallboard ticker.

