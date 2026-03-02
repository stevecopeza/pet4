# PET SLA Automation Hardening Addendum v1.0

Date: 2026-02-26

## Purpose

This document hardens the SLA Automation subsystem prior to expansion of
Work Orchestration. It defines system glue, invariants, idempotency
rules, and migration safety requirements.

This document is ADDITIVE to:

-   docs/08_implementation_blueprint/pre_demo_execution/05_sla_automation_memo.md

------------------------------------------------------------------------

## 1. Architectural Intent

The SLA Clock is:

-   Deterministic
-   Idempotent
-   Concurrency-safe
-   Event-transition driven

It MUST:

-   Never double-dispatch Warning or Breach events
-   Never lose transition state
-   Remain safe under repeated evaluation
-   Remain safe under concurrent execution

------------------------------------------------------------------------

## 2. SLA Clock State Model

``` mermaid
stateDiagram-v2
    [*] --> Active
    Active --> Warning : now >= warning_at
    Warning --> Breached : now >= breach_at
    Breached --> Breached
```

Transitions occur only when crossing threshold boundaries.

No transition → No event dispatch.

------------------------------------------------------------------------

## 3. Required Infrastructure Guarantees

### 3.1 Unique Constraints

`sla_clock_state` MUST enforce:

-   UNIQUE(ticket_id)

This guarantees one clock per ticket.

### 3.2 Row-Level Locking

`SlaAutomationService::evaluate()` MUST:

-   SELECT ... FOR UPDATE the clock state row
-   Compare persisted state to calculated state
-   Dispatch events only on transition
-   Persist new state before releasing lock

------------------------------------------------------------------------

## 4. Scheduler Wiring

System MUST:

-   Register cron hook
-   Be safe if invoked twice simultaneously
-   Evaluate tickets in bounded batches
-   Be restart-safe

Batch evaluation MUST:

-   Only select tickets with active SLAs
-   Exclude closed/resolved tickets (unless policy says otherwise)
-   Support pagination

------------------------------------------------------------------------

## 5. Recalculation Rules

Clock must be recalculated when:

-   SLA policy changes
-   Ticket priority changes
-   Ticket status pauses/unpauses clock
-   Due time is modified

Recalculation MUST:

-   Not re-fire previously dispatched events
-   Adjust future thresholds deterministically

------------------------------------------------------------------------

## 6. "No SLA" Behavior

If SLA is removed or disabled:

Recommended Rule: - Preserve historical clock record - Mark as
inactive - Do NOT delete row

Rationale: Immutability and auditability.

------------------------------------------------------------------------

## 7. Integration Idempotency

Outbound effects (email, webhooks, notifications) MUST be idempotent.

Test requirement: Calling evaluate() twice at breach boundary must
result in: - One TicketBreachedEvent - One downstream notification

------------------------------------------------------------------------

## 8. Required Tests

### Unit Tests

-   Active → Warning transition
-   Warning → Breached transition
-   Double evaluation does not double-fire
-   Concurrent evaluation safe (simulated)

### Integration Tests

-   Scheduler triggers evaluation
-   Batch processing works
-   Migration-safe execution

------------------------------------------------------------------------

## Acceptance Criteria

-   No duplicate events
-   Safe under concurrency
-   Deterministic recalculation
-   Backward-compatible migrations
