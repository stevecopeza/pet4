# PET SLA & Work Orchestration Migration Sequencing Plan v1.0

Date: 2026-02-26

## Purpose

Define the safe introduction order of:

-   SLA Automation Hardening
-   Work Orchestration Hardening

Into existing live customer environments.

This plan assumes: - Forward-only migrations - No down migrations -
Users may skip versions - Backward compatibility is mandatory

------------------------------------------------------------------------

# Phase Overview

``` mermaid
flowchart TD
    A[Phase 1: Schema Introduction] --> B[Phase 2: Passive Logic Deployment]
    B --> C[Phase 3: Idempotent Projection Activation]
    C --> D[Phase 4: Priority Engine Activation]
    D --> E[Phase 5: UI Exposure]
```

------------------------------------------------------------------------

# Phase 1 --- Schema Introduction (No Behaviour Change)

## Objective

Introduce required tables and constraints safely.

## Actions

1.  Create `sla_clock_state` if missing
    -   UNIQUE(ticket_id)
    -   Required indexes
2.  Add WorkItem uniqueness constraint:
    -   UNIQUE(source_type, source_id, context_version)
3.  Do NOT activate scheduler yet
4.  Do NOT register projection listeners yet

## Acceptance Criteria

-   Migration runs on fresh install
-   Migration runs on older install missing tables
-   No runtime behavior change
-   No fatal errors if tables pre-exist

------------------------------------------------------------------------

# Phase 2 --- Passive SLA Logic Deployment

## Objective

Deploy hardened SLA evaluation logic without activating scheduler.

## Actions

1.  Deploy updated `SlaAutomationService`
2.  Ensure service callable manually
3.  Add integration tests verifying:
    -   Idempotency
    -   Concurrency safety

Scheduler remains disabled.

## Acceptance Criteria

-   Manual evaluation safe
-   No duplicate events
-   No regressions in ticket flow

------------------------------------------------------------------------

# Phase 3 --- Projection Activation (WorkItem)

## Objective

Enable idempotent Ticket → WorkItem projection.

## Actions

1.  Register TicketCreatedEvent listener
2.  Ensure projection uniqueness enforced
3.  Add projection tests:
    -   Duplicate event → one WorkItem
    -   Retry safe
    -   Concurrent safe

UI remains hidden if not complete.

## Acceptance Criteria

-   No duplicate WorkItems
-   No performance regression
-   Safe under repeated event dispatch

------------------------------------------------------------------------

# Phase 4 --- SLA Scheduler Activation

## Objective

Activate automated evaluation.

## Actions

1.  Register cron hook
2.  Enable bounded batch evaluation
3.  Monitor logs for duplicate dispatch

## Safeguards

-   Batch size limit
-   Timeout safety
-   No blocking long transactions

## Acceptance Criteria

-   Warning and breach events fire correctly
-   No duplicate downstream effects
-   System remains stable under load

------------------------------------------------------------------------

# Phase 5 --- Queue & Priority Exposure

## Objective

Expose My Queue / Department Queue endpoints.

## Actions

1.  Enable PriorityScoringService
2.  Enforce deterministic ordering
3.  Validate stable tie-break behavior

## UI Exposure Order

1.  Manager-only preview
2.  Controlled rollout
3.  General availability

------------------------------------------------------------------------

# Rollback Strategy

Because migrations are forward-only:

-   Behavioral rollback only
-   Feature flags used for:
    -   Scheduler
    -   Projection listener
    -   Queue visibility

Schema remains intact.

------------------------------------------------------------------------

# Risk Controls

  Risk                      Mitigation
  ------------------------- ----------------------------
  Duplicate projections     Unique constraints
  Double SLA firing         Row-level locking
  Concurrency conflicts     FOR UPDATE + tests
  Performance degradation   Batching
  Backward schema gaps      Defensive migration checks

------------------------------------------------------------------------

# Final Acceptance Gate

Before full release:

-   All migration paths tested
-   All idempotency tests pass
-   No duplicate events in staging
-   No duplicate WorkItems in staging
-   Cron safe under concurrent trigger
