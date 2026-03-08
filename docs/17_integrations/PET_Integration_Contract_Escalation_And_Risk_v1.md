# PET Lifecycle Integration Contract --- Escalation & Risk v1.0

Date: 2026-02-26

## Purpose

Define *when* Escalation & Risk artifacts exist in the lifecycle of
their parent entities (Tickets / Advisory Signals), including explicit
render/creation/mutation rules, prohibited behaviours, and stress-test
scenarios.

This document is REQUIRED prior to implementation.

------------------------------------------------------------------------

## Parent Entities

Primary parents: - Ticket (Support domain) Secondary parents: -
AdvisorySignal (Advisory domain)

Escalations are *derived operational artifacts* that must never mutate
parent truth.

------------------------------------------------------------------------

## 1) Render Rules (when it exists / must not exist)

### Escalation Lists & Widgets MUST render when

-   pet_escalation_enabled == true
-   viewer has permission scope (agent/manager/admin)
-   Escalation status in scope (default OPEN)

### Escalation Lists & Widgets MUST NOT render when

-   pet_escalation_enabled == false
-   viewer lacks scope
-   Escalation tables not present (older schema) → UI must fail fast
    with clear admin error, not fatal

### Escalation Detail MUST render when

-   escalation exists
-   viewer has scope to see its source (ticket/customer/team)

### "ACK/RESOLVE" actions MUST render when

-   viewer is manager+ for the escalation target scope
-   escalation status allows transition

------------------------------------------------------------------------

## 2) Creation Rules (what triggers its creation)

### Escalation MUST be created when

-   pet_escalation_enabled == true
-   An allowed trigger event occurs:
    -   TicketBreachedEvent (if pet_escalation_auto_on_sla_breach ==
        true)
    -   TicketWarningEvent (if pet_escalation_auto_on_sla_warning ==
        true)
    -   AdvisorySignalRaisedEvent (if rules target ADVISORY_SIGNAL)
    -   Manual trigger via admin (optional, explicit)

### Escalation MUST NOT be created when

-   pet_escalation_enabled == false
-   Trigger sub-flag disabled for the trigger type
-   Ticket is resolved/closed if rules specify "active-only" (default:
    active-only)
-   Cooldown / dedupe window indicates an existing OPEN escalation for
    same rule+source

### Dedupe / Idempotency rule

If the same trigger is processed twice: - Exactly one OPEN escalation
may exist per rule+source within cooldown - Second trigger is a no-op

------------------------------------------------------------------------

## 3) Mutation Rules (how it can change)

Escalations are immutable records with explicit status transitions:

Allowed transitions: - OPEN -\> ACKED (AcknowledgeEscalationCommand) -
OPEN -\> RESOLVED (ResolveEscalationCommand, optional direct resolve) -
ACKED -\> RESOLVED (ResolveEscalationCommand)

Disallowed transitions: - RESOLVED -\> any - ACKED -\> OPEN - Editing
severity/source/rule after OPEN

Transition requirements: - Actor identity recorded - Timestamp
recorded - Optional note recorded as transition note (append-only)

------------------------------------------------------------------------

## 4) Prohibited Behaviours (must NOT happen)

-   MUST NOT auto-create escalations on Ticket creation.
-   MUST NOT create escalations when feature flag is disabled.
-   MUST NOT create escalations by reading UI endpoints (no read-side
    side effects).
-   MUST NOT open duplicate OPEN escalations for same rule+source within
    cooldown.
-   MUST NOT mutate escalation fields after opening (only append
    transitions).
-   MUST NOT mutate Ticket state as a side effect of escalation (no auto
    status changes).
-   MUST NOT "inject defaults" into rule criteria_json beyond explicit
    admin input.
-   MUST NOT allow UI to bypass domain transition checks.
-   MUST NOT dispatch EscalationTriggeredEvent more than once for the
    same OPEN transition.

------------------------------------------------------------------------

## 5) Stress-Test Scenarios (cross-boundary, integration-level)

1.  **Feature flag off**
    -   Given pet_escalation_enabled=false
    -   When TicketBreachedEvent fires
    -   Then no escalation is created, no events dispatched, no side
        effects
2.  **Double trigger / retry safety**
    -   Given escalation enabled and a rule matches SLA_BREACH
    -   When TicketBreachedEvent is processed twice (or listener
        retried)
    -   Then only one OPEN escalation exists and only one
        EscalationTriggeredEvent is recorded
3.  **Cooldown enforcement**
    -   Given an OPEN escalation exists for rule+ticket
    -   When breach trigger fires again within cooldown
    -   Then no new escalation is created
4.  **Permission gating**
    -   Given an agent outside scope
    -   When requesting escalation list/detail
    -   Then 403/filtered empty, not data leakage
5.  **ACK/RESOLVE transitions**
    -   Given OPEN escalation
    -   When manager ACKs then RESOLVEs
    -   Then transitions append in order; escalation fields remain
        immutable
6.  **Parent entity independence**
    -   Given an escalation OPEN on a ticket
    -   When other unrelated ticket fields change
    -   Then escalation remains unchanged (no mutation) unless new
        trigger explicitly matches and passes cooldown rules

------------------------------------------------------------------------

## Acceptance Gate

Implementation must not start until: - This contract is approved -
Corresponding domain tests for these scenarios are listed and planned
