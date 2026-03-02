# PET Phase 6 Completion Sprint Plan v1.0 (COMPLETED)

## Status
**COMPLETED** - All Phase 6 blockers resolved.
- OVERALL_PHASE_6_STATUS: PASS
- READY_FOR_DEMO_ENGINE: YES

## Objective

Complete all remaining Phase 6 blockers so that:

-   OVERALL_PHASE_6\_STATUS = PASS
-   READY_FOR_DEMO_ENGINE = YES

Timebox: 3--5 focused engineering days.

------------------------------------------------------------------------

# Sprint Structure

## Day 1 -- DemoPreFlightCheck (Foundation)

### Deliverables

-   `DemoPreFlightCheck` service (Application layer)
-   Health rule registry (extensible)
-   `GET /system/pre-demo-check` endpoint
-   Structured JSON PASS/FAIL output
-   Hard-block mechanism for Demo Engine activation

### Health Check Flow

``` mermaid
flowchart TD
    A[PreFlightCheck] --> B[Check SLA Loop]
    A --> C[Check Events]
    A --> D[Check Projections]
    A --> E[Check Quote Validation]
    B --> F[Aggregate Results]
    C --> F
    D --> F
    E --> F
```

Exit Criteria:

-   Endpoint returns structured result
-   Any FAIL blocks demo activation

------------------------------------------------------------------------

## Day 2 -- Missing Event Implementations

### Required Events

-   EscalationTriggeredEvent
-   MilestoneCompletedEvent
-   ChangeOrderApprovedEvent

### Requirements

-   Event classes created
-   Dispatched at correct domain transitions
-   Registered in event registry
-   Consumed by projections (Feed, Work, Audit)

------------------------------------------------------------------------

## Day 3 -- Quote Invariant Hardening

### Required Fixes

-   Explicit SKU field on QuoteCatalogItem
-   Explicit role_id on service lines
-   validateReadiness enforces invariants
-   Acceptance aborts transaction on violation

------------------------------------------------------------------------

## Day 4 -- SLA Idempotency & Concurrency Verification

### Required Tests

-   Duplicate cron invocation does not duplicate events
-   Escalation stage increments only once
-   Pause/resume behaves correctly
-   Ticket closure halts evaluation

------------------------------------------------------------------------

## Day 5 -- Full Phase 6 Audit Re-run

Re-run Phase 6 Audit prompt.

Goal:

OVERALL_PHASE_6\_STATUS: PASS\
READY_FOR_DEMO_ENGINE: YES

------------------------------------------------------------------------

# Definition of Done

Phase 6 complete only when:

-   SLA heartbeat deterministic
-   Quote invariants enforced
-   Required events implemented
-   PreFlightCheck blocking logic active
-   Audit returns PASS

END OF DOCUMENT
