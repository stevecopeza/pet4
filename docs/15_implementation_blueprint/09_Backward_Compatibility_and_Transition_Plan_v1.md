STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# Backward Compatibility and Transition Plan (v1)

## Constraint

Users may skip versions. Therefore migrations and code must tolerate mixed states.

## Key compatibility hazards

- Existing time entries reference tasks only.
- Existing quote acceptance creates projects/tasks, not tickets.
- Existing UIs may rely on tasks.

## Strategy: additive bridging, then gradual cutover

### Phase A — Additive schema + dual-write where needed
- Add `ticket_id` to time entries (nullable).
- Add `ticket_id` to tasks (nullable).
- Extend tickets to support project/internal contexts.
- Ensure submit path resolves ticket via task when possible.

### Phase B — Backfill and projection
- Create tickets for existing tasks; backfill tasks.ticket_id.
- Backfill time_entries.ticket_id via task mapping.
- Ensure idempotency.

### Phase C — Change quote acceptance pipeline
- On acceptance, create one ticket per sold labour item with `sold_minutes` locked and `is_baseline_locked = 1`.
- No draft tickets during quoting. No baseline/execution clone.
- Optionally create tasks as a projection for compatibility views.
- Change orders create new tickets linked via `change_order_source_ticket_id`.

### Phase D — UI cutover
- Gradually move time logging UI to ticket-first selection.
- Keep task-based selection as legacy that resolves to ticket.

## Mixed-state tolerance rules

At runtime, code must handle:
- time entry with ticket_id present (new path)
- time entry with ticket_id NULL but task.ticket_id present (bridge path)
- time entry with both NULL (draft only; cannot submit)

## Data never deleted
All changes are additive; no destructive transformations.
