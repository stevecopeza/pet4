STATUS: PARTIALLY STALE — SEE UPDATES
SCOPE: Ticket Backbone Correction
VERSION: v2
SUPERSEDES: v1
DATE: 2026-03-06

# Ticket Backbone – Planning State (v2)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

## Current state

- Documentation pack for Ticket Backbone is integrated into the main docs hierarchy and has been updated to v2 (2026-03-06).
- Architecture decisions have been formally recorded in `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.
- Schema migrations for Phase 1 (M1–M5) have been implemented. The database is ready for ticket-first operation.
- Application layer (quote acceptance → ticket creation) is NOT yet implemented. The listener still creates Tasks, not Tickets.

## Key architecture decisions (binding)

- **No draft tickets during quoting.** Quote builder manages its own task records.
- **One ticket per sold item at acceptance.** Immutable `sold_minutes`, `is_baseline_locked = 1`.
- **No baseline/execution clone model.** Single ticket, no `ticket_mode`.
- **Change orders** create new tickets linked via `change_order_source_ticket_id`.
- **Leaf-only time logging.** Unsplit tickets are leaves. Rollups reject time.
- **`baseline_locked` is a boolean property, not a lifecycle status.**

See decision record for full details.

## Drift summary

- Current production implementation still uses tasks as primary work units for project delivery.
- Time entries have `ticket_id` column (populated for support tickets, not for project tasks).
- Quote acceptance currently creates projects/tasks without ticket linkage.
- Work orchestration supports tickets and project tasks but is not yet normalized to tickets as the primary source.

## Approved roadmap phases (updated)

- Phase 0: ✅ Pre-implementation guardrails and documentation freeze.
- Phase 1: ✅ Additive schema foundation for tickets, time entries, tasks, and quote labour tables.
- Phase 2: Safe backfill and bridging between tasks, tickets, and time entries.
- Phase 3: Time submission enforcement gate using ticket_id and leaf-only logging.
- Phase 4: Quote acceptance ticket creation (one ticket per sold item, sold_minutes locked). **Replaces old Phase 4 (draft tickets) + Phase 5 (baseline/execution clone).**
- Phase 5: WorkItem projection alignment.
- Phase 6: SLA agreement and entitlement integration (if in scope).
- Phase 7: UI cutover to ticket-first workflows and gradual decommissioning of task-only mode.

## Development status

- Schema migrations (Phase 1) are complete.
- Application layer changes (Phases 2–4) have NOT been started.
- No code changes have been made to the quote acceptance path or time submission enforcement.

## Gating conditions before further development

- Architecture decisions document (`02_Ticket_Architecture_Decisions_v1.md`) is approved.
- Implementation scope is confirmed to be additive-only with forward-only migrations.
- Dedicated execution capacity is allocated.
- A clear rollback and incident response plan exists for enforcement changes.
