# PET Event Registry Addendum --- Escalation & Risk v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## New/Confirmed Domain Events

-   EscalationTriggeredEvent (confirm dispatch)
-   EscalationAcknowledgedEvent (new)
-   EscalationResolvedEvent (new)

## Dispatch Points

-   TicketWarningEvent listener -\>
    EscalationEvaluationService.evaluateForTicketWarning()
-   TicketBreachedEvent listener -\>
    EscalationEvaluationService.evaluateForTicketBreach()
-   AdvisorySignalRaisedEvent listener (for SPOF/risk) -\>
    EscalationEvaluationService.evaluateForAdvisorySignal()

## Projections / Feed

-   FeedProjection should record:
    -   escalation opened (severity, source ref)
    -   escalation acknowledged
    -   escalation resolved

## Idempotency

-   EscalationTriggeredEvent dispatched only on OPEN transition (dedupe
    by open_dedupe_key).
-   Acknowledge/Resolve commands reject illegal transitions (409).

## Acceptance Criteria

-   Event registry doc updated to include new events
-   Listeners registered and tested
