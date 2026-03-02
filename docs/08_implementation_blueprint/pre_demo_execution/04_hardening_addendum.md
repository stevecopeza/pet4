# PET Phase 6 Hardening Addendum -- Pre‑Demo Readiness Clarification v1.0

## Purpose

This document clarifies and hardens **Phase 6 (Pre‑Demo Readiness)**.\
It expands SLA automation, Quote domain validation, and
DemoPreFlightCheck requirements to eliminate ambiguity before
implementation.

This is an execution-grade directive.

------------------------------------------------------------------------

# 1. SLA Automation Engine -- Hardening Requirements

## 1.1 Architectural Objective

The SLA engine must behave as a deterministic, idempotent, stateful
automation loop.

It must not: - Dispatch duplicate events - Double-escalate -
Miscalculate business time - Breach outside defined working windows

------------------------------------------------------------------------

## 1.2 Required Components

### Tables

-   `sla`
-   `sla_versions`
-   `sla_escalation_rules`
-   `ticket_sla_bindings`
-   `sla_clock_state`

### Required Fields (sla_clock_state)

-   ticket_id (FK)
-   warning_at (datetime)
-   breach_at (datetime)
-   paused_flag (boolean)
-   escalation_stage (int)
-   last_evaluated_at (datetime)
-   last_event_dispatched (enum: none, warning, breached)

------------------------------------------------------------------------

## 1.3 Automation Loop Contract

Runs every 1--5 minutes.

### Required Behaviour

1.  Fetch open tickets
2.  Recalculate business time
3.  Determine SLA state
4.  Compare against persisted clock state
5.  Dispatch event only if state transitioned
6.  Persist new clock state

------------------------------------------------------------------------

## 1.4 Idempotency Guarantee

State transition must follow this logic:

``` mermaid
stateDiagram-v2
    [*] --> Active
    Active --> Warning : warning threshold crossed
    Warning --> Breached : breach threshold crossed
    Warning --> Active : pause/resume recalculation
    Breached --> Escalated : escalation rule triggered
```

Events may only fire on state transition.

------------------------------------------------------------------------

## 1.5 Event Dispatch Flow

``` mermaid
sequenceDiagram
    participant Cron
    participant SlaAutomationService
    participant Ticket
    participant EventBus
    participant ProjectionHandlers

    Cron->>SlaAutomationService: run()
    SlaAutomationService->>Ticket: recalc SLA
    SlaAutomationService->>EventBus: TicketWarningEvent
    EventBus->>ProjectionHandlers: update work items/feed
```

------------------------------------------------------------------------

## 1.6 Exit Criteria

-   Ticket configured to breach in 30 minutes auto-breaches.
-   Escalation occurs once only.
-   Re-running loop produces no duplicate events.

------------------------------------------------------------------------

# 2. Quote Domain Readiness Gates -- Hardening Requirements

## 2.1 Architectural Objective

Quote acceptance must be protected by domain-level invariants.

Validation must live in Domain layer, not UI.

------------------------------------------------------------------------

## 2.2 Required Invariants

### Product Lines

-   Must reference product SKU
-   Must NOT contain rate/hour fields

### Service Lines

-   Must reference role_id
-   Must contain hours and rate

### Implementation Section

-   At least one milestone
-   At least one task per milestone

### Payment Plan

-   Must resolve to canonical schedule entries
-   Sum(schedule) == quote total

------------------------------------------------------------------------

## 2.3 Validation Flow

``` mermaid
flowchart TD
    A[Accept Quote Command] --> B[Quote.validate()]
    B -->|FAIL| C[Throw Domain Exception]
    B -->|PASS| D[Transition to Accepted]
    D --> E[Emit QuoteAcceptedEvent]
    E --> F[Create Project]
```

------------------------------------------------------------------------

## 2.4 Mandatory Enforcement

-   AcceptQuoteHandler must call validate()
-   Validation failure must abort transaction
-   No partial state allowed

------------------------------------------------------------------------

## 2.5 Exit Criteria

-   Invalid quote cannot be accepted
-   Valid quote always produces ProjectCreatedEvent

------------------------------------------------------------------------

# 3. Event Registry -- Demo Critical Coverage

Before Demo Engine work:

Required implemented events:

-   QuoteAcceptedEvent
-   ProjectCreatedEvent
-   MilestoneCompletedEvent
-   TicketCreatedEvent
-   TicketWarningEvent
-   TicketBreachedEvent
-   EscalationTriggeredEvent
-   ChangeOrderApprovedEvent

------------------------------------------------------------------------

## 3.1 Event → Projection Verification

``` mermaid
flowchart LR
    EventBus --> WorkItemProjection
    EventBus --> FeedProjection
    EventBus --> CapacityProjection
```

Each handler must be registered and verifiable.

------------------------------------------------------------------------

# 4. DemoPreFlightCheck -- System Diagnostic Contract

## 4.1 Purpose

Prevents Demo Engine activation on unstable system state.

------------------------------------------------------------------------

## 4.2 Required Checks

-   SLA scheduler active
-   SLA automation functional
-   Quote.validate enforced
-   Required events dispatching
-   Projection handlers active
-   No fatal integrity exceptions

------------------------------------------------------------------------

## 4.3 API Contract

`GET /system/pre-demo-check`

Response:

``` json
{
  "sla_automation": "PASS",
  "quote_validation": "PASS",
  "event_registry": "PASS",
  "projection_handlers": "PASS",
  "integrity_state": "PASS"
}
```

------------------------------------------------------------------------

## 4.4 Activation Rule

If any critical check FAILS → Demo Engine activation must be blocked.

------------------------------------------------------------------------

# 5. Final Directive

Phase 6 is not complete when code compiles.

It is complete only when:

-   Automation loop deterministic
-   Domain invariants enforced
-   Event dispatch verified
-   PreFlightCheck returns PASS

Only then may Demo Engine implementation resume.

------------------------------------------------------------------------

END OF DOCUMENT
