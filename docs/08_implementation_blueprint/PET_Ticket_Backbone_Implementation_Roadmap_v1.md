STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# PET Ticket Backbone — Sequenced Implementation Roadmap (v1)

Generated: 2026-02-16 16:36 UTC

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

# Phase 4 — Quote Draft Ticket Creation

## Scope
- Labour quote tasks create draft tickets immediately.
- ticket_id stored on quote task row.
- Idempotent creation logic.

## Constraints
- Do not redesign UI.
- Do not break existing quote editing.

## Verification
- Creating labour item results in draft ticket row.
- Editing quote does not duplicate tickets.

## Risk Level: Medium

---

# Phase 5 — Quote Acceptance Realignment

## Scope
- On QuoteAccepted:
  - Lock baseline.
  - Clone execution tickets.
  - Link execution tickets to project.
  - Link tasks.ticket_id to execution tickets.

## Hard Invariants
- Baseline sold values immutable.
- Execution tickets may split; baseline never edited.

## Verification
- Accepting quote results in:
  - Baseline tickets locked.
  - Execution tickets present.
  - Tasks mapped to execution tickets.

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
- Quote labour always has draft ticket.
- Project execution always composed of tickets.
- Support and project tickets are same entity.
- Backward compatibility preserved across skipped versions.
