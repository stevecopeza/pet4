# PET Event Registry Addendum --- Support Helpdesk v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Events Used (existing)

-   TicketCreatedEvent
-   TicketResolvedEvent (or equivalent)
-   TicketWarningEvent
-   TicketBreachedEvent

## New (if missing)

-   TicketAssignedToTeamEvent
-   TicketAssignedToEmployeeEvent
-   TicketPulledFromTeamEvent

## Dispatch Points

-   Assignment service commands dispatch assignment events on successful
    transitions.
-   Helpdesk overview does not dispatch events (read-only).

## Projections

-   FeedProjection records assignment changes and resolution events.

## Acceptance Criteria

-   Assignment events exist and are registered
-   Feed shows assignment transitions
