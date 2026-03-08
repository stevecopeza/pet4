STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v2
SUPERSEDES: v1
DATE: 2026-03-06

# PET Ticket Backbone — Sequenced Implementation Roadmap (v2)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

Generated: 2026-02-16 16:36 UTC | Updated: 2026-03-06

This roadmap translates the Ticket Backbone Master Specification into an ordered, phase-gated execution plan with explicit risk controls.

---

# Phase 0 — Pre-Implementation Guardrails (Mandatory)

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

# Phase 1 — Additive Schema Foundation

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

# Phase 2 — Safe Backfill & Bridging

## Scope
- Create tickets for all existing tasks.
- Backfill tasks.ticket_id.
- Backfill time_entries.ticket_id via task mapping.
- Implement CLI command for re-runnable backfill.

## Hard Rules
- Never mutate historical time values.
- No deletion of legacy tasks.
- Flag ambiguous records only; do not guess.

## Verification
- Row counts match expected mappings.
- Re-running backfill produces zero changes (idempotent).
- Time submission still works for legacy entries.

## Risk Level: Medium–High
Data integrity risk if backfill mapping incorrect.

---

# Phase 3 — Time Enforcement Gate

## Scope
- Enforce ticket_id requirement at submission boundary.
- Prevent submission against roll-up tickets.
- Preserve draft timers with NULL ticket_id.

## Tests Required
- Cannot submit without ticket.
- Cannot submit to parent ticket.
- Legacy task-only entries auto-resolve.

## Risk Level: High
Directly affects operational workflows.

Mitigation:
- Feature flag for enforcement (temporary).
- Clear error messaging.

---

# Phase 4 — Quote Acceptance Ticket Creation

> **Note:** Phase 4 (Quote Draft Ticket Creation) from v1 has been REMOVED per architecture decisions. No tickets are created during quoting. The quote builder manages its own task records.

## Scope
- On QuoteAccepted:
  - For each labour QuoteTask, create one ticket with `sold_minutes` locked and `is_baseline_locked = 1`.
  - Set `root_ticket_id` = self on each created ticket.
  - Store `ticket_id` on the QuoteTask record.
  - Link tickets to the project being created.
  - Link tasks.ticket_id to the created tickets (backward compat).

## What does NOT happen
- No "baseline ticket" is created as a separate record.
- No "execution ticket clone" is created.
- No `ticket_mode` is set.

## Hard Invariants
- `sold_minutes` and `sold_value_cents` immutable once set.
- `is_baseline_locked = 1` on all tickets created from accepted quotes.

## Verification
- Accepting quote results in:
  - One ticket per sold labour item with locked `sold_minutes`.
  - Tickets linked to project.
  - Tasks mapped to tickets.
  - No duplicate tickets on re-acceptance attempts.

## Risk Level: High
Core commercial transition logic.

---

# Phase 6 — SLA Agreement & Entitlement (If in scope)

## Scope
- Introduce Agreement entity.
- Entitlement ledger.
- Consumption tracking per time entry.

## Risk Level: Medium
Isolated but financially sensitive.

---

# Phase 7 — UI Cutover (Gradual)

## Scope
- Make Ticket primary selection in time logging.
- Keep Task as compatibility selection resolving to ticket.

## Risk Level: Low–Medium
Primarily UX adjustments.

---

# Phase 8 — Decommission Legacy Task-Only Mode (Optional Future)

## Scope
- Prevent new task creation without ticket.
- Move toward ticket-first UI everywhere.

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
