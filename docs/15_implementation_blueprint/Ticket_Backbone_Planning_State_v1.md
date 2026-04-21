STATUS: UPDATED 2026-04-20
SCOPE: Ticket Backbone Correction
VERSION: v3
SUPERSEDES: v2
DATE: 2026-03-06
UPDATED: 2026-04-20 — Phase 4 (quote acceptance → tickets) is now COMPLETE.

# Ticket Backbone – Planning State (v3)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

## Current state (as of 2026-04-20)

- Documentation pack for Ticket Backbone is integrated into the main docs hierarchy and has been updated to v2 (2026-03-06).
- Architecture decisions have been formally recorded in `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.
- Schema migrations for Phase 1 (M1–M5) have been implemented. The database is ready for ticket-first operation.
- **Application layer (quote acceptance → ticket creation) is NOW IMPLEMENTED.**
  `AcceptQuoteHandler` calls `CreateProjectTicketHandler` which creates `Domain\Support\Entity\Ticket`
  records with all backbone fields (`soldMinutes`, `estimatedMinutes`, `isBaselineLocked=true`,
  `projectId`, `quoteId`, `isRollup`, `lifecycleOwner='project'`, `primaryContainer='project'`).
- `AddTaskHandler` is disabled — throws `DomainException('Legacy project task creation is disabled')`.
- `CreateProjectFromQuoteListener` creates the Project record only (no Tasks).
- `LogTimeHandler` enforces `canAcceptTimeEntries()` against Tickets.
- `WorkItemProjector::onTicketCreated()` handles project ticket work item projection.

## Key architecture decisions (binding)

- **No draft tickets during quoting.** Quote builder manages its own task records.
- **One ticket per sold item at acceptance.** Immutable `sold_minutes`, `is_baseline_locked = 1`.
- **No baseline/execution clone model.** Single ticket, no `ticket_mode`.
- **Change orders** create new tickets linked via `change_order_source_ticket_id`.
- **Leaf-only time logging.** Unsplit tickets are leaves. Rollups reject time.
- **`baseline_locked` is a boolean property, not a lifecycle status.**

See decision record for full details.

## Drift summary (updated 2026-04-20)

- ~~Current production implementation still uses tasks as primary work units for project delivery.~~ **RESOLVED.**
- ~~Quote acceptance currently creates projects/tasks without ticket linkage.~~ **RESOLVED.**
- ~~Work orchestration supports tickets and project tasks but is not yet normalized.~~ **RESOLVED** — `WorkItemProjector` now handles `onTicketCreated` only; no `onProjectTaskCreated` handler remains.
- `wp_pet_tasks` table still exists but is never written to by active code. `ticket_id` bridge column exists but backfill (Phase 2) is not complete for historical rows.
- Admin project UI (`types.ts Project.tasks: Task[]`) still references the legacy Task shape — needs Phase 7 cutover.
- `Domain\Delivery\Entity\Task` class remains as dead code (Phase 8 cleanup pending).

## Approved roadmap phases (updated 2026-04-20)

- Phase 0: ✅ Pre-implementation guardrails and documentation freeze.
- Phase 1: ✅ Additive schema foundation for tickets, time entries, tasks, and quote labour tables.
- Phase 2: ⚠️ Backfill of `wp_pet_tasks.ticket_id` for historical rows not yet done. New rows are not written. Low priority since `AddTaskHandler` is disabled.
- Phase 3: ✅ Time submission enforcement gate — `LogTimeHandler` enforces `canAcceptTimeEntries()` against Tickets.
- Phase 4: ✅ **COMPLETE (2026-04-20)** — Quote acceptance creates Tickets via `CreateProjectTicketHandler`. Sold minutes locked. Rollup + child pattern implemented. Idempotent (duplicate-safe via `findByProvisioningKey`).
- Phase 5: ✅ WorkItem projection alignment — `WorkItemProjector::onTicketCreated()` handles project tickets.
- Phase 6: ❌ SLA agreement and entitlement integration for delivery tickets — not started.
- Phase 7: ❌ Admin UI cutover — `types.ts Project.tasks: Task[]` needs replacing with Tickets; admin project view needs update.
- Phase 8: ❌ Legacy decommission — remove `Domain\Delivery\Entity\Task`, `AddTaskHandler`, `AddTaskCommand`, eventually drop `wp_pet_tasks`.

## Development status

- Phases 0, 1, 3, 4, 5: ✅ Complete.
- Phase 2: ⚠️ Partial — new writes disabled, historical backfill not run.
- Phases 6, 7, 8: ❌ Not started.

## Gating conditions for remaining phases

- Phase 6 (SLA for delivery): Requires agreement on entitlement model. Not blocking.
- Phase 7 (UI cutover): Can proceed independently. Low risk — admin panel change only.
- Phase 8 (legacy cleanup): Gate on Phase 7 being stable. Requires migration to drop `wp_pet_tasks`.
