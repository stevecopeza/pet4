# PET DemoPreFlightCheck -- Technical Specification v1.0

## Purpose

DemoPreFlightCheck is a system-level readiness contract that validates
system health before enabling the Demo Engine.

It must hard-block demo activation if critical checks fail.

------------------------------------------------------------------------

# 1. Service Contract

Class: `DemoPreFlightCheck`\
Layer: Application

Method:

run(): PreFlightResult

PreFlightResult:

{ sla_automation: PASS \| FAIL, event_registry: PASS \| FAIL,
projection_handlers: PASS \| FAIL, quote_validation: PASS \| FAIL,
overall: PASS \| FAIL }

------------------------------------------------------------------------

# 2. Required Checks

## 2.1 SLA Automation

-   Cron hook registered
-   SlaAutomationService resolvable
-   sla_clock_state table exists
-   TicketWarningEvent dispatch verified

## 2.2 Event Registry

Confirm dispatch wiring for:

-   QuoteAcceptedEvent
-   ProjectCreatedEvent
-   TicketWarningEvent
-   TicketBreachedEvent
-   EscalationTriggeredEvent
-   MilestoneCompletedEvent
-   ChangeOrderApprovedEvent

## 2.3 Projection Handlers

Verify listeners exist for:

-   FeedProjection
-   WorkItemProjection
-   CapacityProjection

## 2.4 Quote Validation

-   validateReadiness() exists
-   AcceptQuoteHandler invokes validation
-   Failure aborts state transition

------------------------------------------------------------------------

# 3. Activation Blocking Logic

``` mermaid
flowchart TD
    A[Demo Activation Attempt] --> B[Run PreFlightCheck]
    B -->|PASS| C[Activate Demo]
    B -->|FAIL| D[Block Activation + Return Errors]
```

Demo activation MUST abort if overall != PASS.

------------------------------------------------------------------------

# 4. API Endpoint

GET /system/pre-demo-check

Returns structured JSON with individual subsystem results.

No partial activation allowed.

------------------------------------------------------------------------

# Definition of Done

PreFlightCheck implemented when:

-   Endpoint exists
-   Hard-block enforced
-   All required checks implemented
-   Structured JSON returned
-   Unit tests cover PASS and FAIL scenarios

END OF DOCUMENT
