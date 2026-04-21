STATUS: AUTHORITATIVE — PARTIALLY COMPLETE (see phase statuses below)
SCOPE: Ticket Backbone Correction
VERSION: v3
SUPERSEDES: v2
DATE: 2026-03-06
UPDATED: 2026-04-20 — Phases 0, 1, 3, 4, 5 complete. Phases 6, 7, 8 remain.

# PET Ticket Backbone — Sequenced Implementation Roadmap (v2)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

Generated: 2026-02-16 16:36 UTC | Updated: 2026-03-06

This roadmap translates the Ticket Backbone Master Specification into an ordered, phase-gated execution plan with explicit risk controls.

---

# Phase 0 — Pre-Implementation Guardrails ✅ COMPLETE

## Objectives
- Freeze documentation baseline.
- Confirm invariant agreement: “All person work activity must be tied to a Ticket.”
- Confirm additive-only migration policy.
- Confirm no destructive schema changes permitted.

## Exit Criteria
- Board approval of Master Spec.
- Dev team acknowledges no redesign beyond scope.

## Risk Level: Low
Organisational alignment risk only.

---

# Phase 1 — Additive Schema Foundation ✅ COMPLETE

## Scope
- Extend `wp_pet_tickets` with cross-context fields.
- Create `wp_pet_ticket_links`.
- Add `ticket_id` to `wp_pet_time_entries` (nullable).
- Add `ticket_id` to `wp_pet_tasks` (nullable).
- Add `ticket_id` to quote labour tables.

## Constraints
- Idempotent migrations.
- No removal of task_id from time entries.
- No lifecycle changes yet.

## Verification
- Schema diff validated.
- Existing UI still operational.
- No fatal errors on environments skipping versions.

## Risk Level: Medium
Schema drift risk if migrations not fully idempotent.

---

# Phase 2 — Safe Backfill & Bridging ⚠️ PARTIAL

## Status (2026-04-20)
New Task creation is disabled (`AddTaskHandler` throws). Historical `wp_pet_tasks` rows
have not been backfilled to `ticket_id`. Since no new tasks are written, this is low priority
unless historical delivery data needs to be surfaced in ticket-based views.

## Scope
- ~~Create tickets for all existing tasks.~~ Superseded — `AddTaskHandler` is now disabled.
- Backfill `tasks.ticket_id` for any historical rows (optional / low priority).
- Backfill `time_entries.ticket_id` for any legacy task-linked time entries (optional).
- Implement CLI command for re-runnable backfill if historical data migration is required.

## Hard Rules
- Never mutate historical time values.
- No deletion of legacy task rows.
- Flag ambiguous records only; do not guess.

## Risk Level: Low (reduced — no active task writes)

---

# Phase 3 — Time Enforcement Gate ✅ COMPLETE

## What was implemented
- `LogTimeHandler` fetches the Ticket by `ticketId` and calls `$ticket->canAcceptTimeEntries()`.
- Throws `DomainException` if the ticket cannot accept time (closed, rollup, unassigned support ticket, non-in_progress project ticket).
- Time entries store `ticket_id` directly — no task intermediary.

---

# Phase 4 — Quote Acceptance Ticket Creation ✅ COMPLETE (2026-04-20)

> **Note:** Phase 4 (Quote Draft Ticket Creation) from v1 has been REMOVED per architecture decisions. No tickets are created during quoting. The quote builder manages its own QuoteTask records.

## What was implemented
- `AcceptQuoteHandler::createTicketsFromQuote()` iterates quote components:
  - `ImplementationComponent`: each milestone task row becomes a Ticket. Multi-task components get a rollup Ticket (`isRollup=true`) with child Tickets referencing it via `parentTicketId`.
  - `OnceOffServiceComponent`: each unit becomes a Ticket. Multi-unit components get a rollup + children.
- `CreateProjectTicketHandler` handles deduplication via `findByProvisioningKey(projectId, sourceComponentId, parentTicketId)` — idempotent on re-acceptance attempts.
- All created Tickets have: `soldMinutes` and `soldValueCents` set, `isBaselineLocked=true`, `lifecycleOwner='project'`, `primaryContainer='project'`, `quoteId` set, `projectId` set.
- `TicketCreated` event dispatched for each ticket — WorkItem projection fires.

## Verification status
- ✅ Rollup + child pattern working.
- ✅ Duplicate-safe.
- ✅ Project linked.
- ⚠️ `tasks.ticket_id` NOT backfilled (Phase 2 deferred).
- ⚠️ WBS post-acceptance splitting not yet implemented.

---

# Phase 5 — WorkItem Projection Alignment ✅ COMPLETE

## What was implemented
- `WorkItemProjector::onTicketCreated()` handles project tickets (lifecycle_owner='project')
  as well as support tickets.
- No `onProjectTaskCreated` handler remains — Tasks are dead code.

---

# Phase 6 — SLA Agreement & Entitlement ❌ NOT STARTED

## Scope
- Introduce Agreement entity.
- Entitlement ledger.
- Consumption tracking per time entry.

## Risk Level: Medium
Isolated but financially sensitive. Not blocking any current workflows.

---

# Phase 7 — Admin UI Cutover ❌ NOT STARTED

## Scope
- Update `types.ts` `Project` interface: replace `tasks: Task[]` with delivery tickets.
- Update admin project view to show delivery Tickets (with lifecycle status, sold minutes, time logged).
- Remove legacy Task UI components from admin where no longer needed.

## Risk Level: Low–Medium
Admin panel only. Portal already uses Tickets natively.

---

# Phase 8 — Legacy Code Decommission ❌ NOT STARTED (Optional)

## Scope
- Remove `Domain\Delivery\Entity\Task` (dead code).
- Remove `Application\Delivery\Command\AddTaskHandler` and `AddTaskCommand`.
- Remove `Application\Delivery\Command\AddTaskCommand`.
- Write a forward migration to drop `wp_pet_tasks` (or archive it).
- Remove `project_task` source type from `WorkItem` if no longer needed.

## Gate
Complete Phase 7 and confirm no production data depends on `wp_pet_tasks` before running.

---

# Risk Summary Matrix

| Phase | Risk | Impact | Mitigation |
|-------|------|--------|------------|
| 1 | Medium | Schema breakage | Idempotent migrations |
| 2 | Medium–High | Data misalignment | Safe mapping + CLI |
| 3 | High | User workflow disruption | Feature flag + tests |
| 4 | Medium | Quote duplication bugs | Idempotent draft logic |
| 5 | High | Commercial baseline corruption | Immutable baseline guard |
| 6 | Medium | Financial miscalc | Ledger audit tests |
| 7 | Low–Medium | UX friction | Gradual cutover |

---

# Success Definition

The roadmap is complete when:

- No submitted time entry exists without ticket_id.
- Quote acceptance creates one ticket per sold labour item with immutable `sold_minutes`.
- Project execution always composed of tickets (WBS tree).
- Support and project tickets are same entity.
- Change orders create new tickets linked via `change_order_source_ticket_id`.
- Backward compatibility preserved across skipped versions.
