STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# Ticket Backbone – Planning State (v1)

## Current state

- Documentation pack for Ticket Backbone is integrated into the main docs hierarchy.
- Principles, data model, lifecycle, quote flow, time enforcement, catalog/roles/rates, SLA/entitlement, work orchestration, and backward compatibility are documented as authoritative.
- No Ticket Backbone migrations, services, handlers, or UI changes have been implemented in code.

## Confirmed invariant

- All person work activity must be tied to a Ticket.
- Ticket is the single universal work unit for time-bearing human activity.
- Accepted quotes, submitted/locked time, and baseline sold records are immutable; corrections are additive.

## Drift summary

- Current production implementation still uses tasks as primary work units for project delivery.
- Time entries reference task_id only; ticket_id is not yet enforced at submission boundary.
- Quote acceptance currently creates projects/tasks without ticket linkage.
- Work orchestration supports tickets and project tasks but is not yet normalized to tickets as the primary source.

## Approved roadmap phases

- Phase 0: Pre-implementation guardrails and documentation freeze.
- Phase 1: Additive schema foundation for tickets, time entries, tasks, and quote labour tables.
- Phase 2: Safe backfill and bridging between tasks, tickets, and time entries.
- Phase 3: Time submission enforcement gate using ticket_id and leaf-only logging.
- Phase 4: Quote draft ticket creation for labour items.
- Phase 5: Quote acceptance realignment to baseline and execution tickets.
- Phase 6: SLA agreement and entitlement integration (if in scope).
- Phase 7: UI cutover to ticket-first workflows and gradual decommissioning of task-only mode.

## Development has NOT yet begun

- As of this planning state, Ticket Backbone work is at documentation and roadmap alignment only.
- No code, migrations, or database schema changes have been made under the Ticket Backbone roadmap.
- Any existing support ticket or work orchestration changes predate this planning state and are not part of the Ticket Backbone implementation.

## Gating conditions before development may start

- Documentation pack remains frozen and approved as the authoritative source.
- Commercial, governance, and technical stakeholders explicitly confirm the Ticket Backbone invariant and backward compatibility requirements.
- Implementation scope is confirmed to be additive-only with forward-only migrations.
- Dedicated execution capacity is allocated for the multi-phase roadmap, including testing and backfill verification.
- A clear rollback and incident response plan exists for any enforcement changes that affect time submission or quote acceptance.
