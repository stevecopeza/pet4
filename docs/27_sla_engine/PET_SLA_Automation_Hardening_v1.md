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

### 2a. Tiered SLA Clock State Extensions

For tiered SLAs, the clock state additionally tracks:
- `active_tier_priority` — which tier is currently governing the clock
- `tier_elapsed_business_minutes` — minutes elapsed in current tier
- `carried_forward_percent` — percentage carried from previous tier
- `total_transitions` — count of tier boundary crossings

Tier transitions are recorded in `sla_clock_tier_transitions` for
audit. The state machine above applies **per tier** — a ticket may
reach Warning in Tier 1, transition to Tier 2, and return to Active
state relative to Tier 2's targets (with carry-forward applied).

A ticket may accumulate multiple breach events across tiers.
See docs_27_sla_engine_08_tiered_sla_spec.md for full algorithm.

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
-   Tier boundary crossed (tiered SLAs)
-   Manual tier override applied (tiered SLAs)

Recalculation MUST:

-   Not re-fire previously dispatched events
-   Adjust future thresholds deterministically
-   For tiered SLAs: apply carry-forward cap at each tier transition
-   For tiered SLAs: re-evaluate escalation thresholds against new
    tier's target after transition

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
-   Tier transition applies carry-forward cap correctly
-   Tier transition after breach enters new tier at cap%
-   Manual tier override records audit trail
-   Escalation re-evaluation after tier transition

### Integration Tests

-   Scheduler triggers evaluation
-   Batch processing works
-   Migration-safe execution
-   Tier boundary crossing during cron batch evaluation
-   Multiple transitions in single ticket lifecycle

------------------------------------------------------------------------

## Acceptance Criteria

-   No duplicate events
-   Safe under concurrency
-   Deterministic recalculation
-   Backward-compatible migrations
